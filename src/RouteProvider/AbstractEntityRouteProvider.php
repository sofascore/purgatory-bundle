<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\RouteProvider;

use Doctrine\ORM\PersistentCollection;
use Psr\Container\ContainerInterface;
use Sofascore\PurgatoryBundle\Cache\Configuration\Configuration;
use Sofascore\PurgatoryBundle\Cache\Configuration\ConfigurationLoaderInterface;
use Sofascore\PurgatoryBundle\Cache\Configuration\Subscriptions;
use Sofascore\PurgatoryBundle\Exception\InvalidIfExpressionResultException;
use Sofascore\PurgatoryBundle\Exception\LogicException;
use Sofascore\PurgatoryBundle\Listener\Enum\Action;
use Sofascore\PurgatoryBundle\RouteParamValueResolver\ValuesResolverInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * @internal
 *
 * @implements RouteProviderInterface<object>
 */
abstract class AbstractEntityRouteProvider implements RouteProviderInterface
{
    /**
     * @param array<string, array{mixed, mixed}|PersistentCollection<array-key, object>> $entityChangeSet
     *
     * @return array<int, string>
     */
    abstract protected function getChangedProperties(object $entity, array $entityChangeSet): array;

    private ?Configuration $configuration = null;
    /**
     * Subscription ids by configuration key and index, the configuration doesn't change once loaded,
     * so each subscription is serialized and hashed only the first time it is seen.
     *
     * @var array<non-empty-string, array<int, string>>
     */
    private array $subscriptionIds = [];

    public function __construct(
        private readonly ConfigurationLoaderInterface $configurationLoader,
        private readonly ?ExpressionLanguage $expressionLanguage,
        private readonly ContainerInterface $routeParamValueResolverLocator,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    final public function provideRoutesFor(Action $action, object $entity, array $entityChangeSet): iterable
    {
        $class = $entity::class;
        $properties = $this->getChangedProperties($entity, $entityChangeSet);
        $processedSubscriptionIds = [];

        do {
            if (null !== $subscriptions = $this->getSubscriptions(key: $class)) {
                yield from $this->processValidSubscriptions($subscriptions, $entityChangeSet, $entity, $action, $processedSubscriptionIds);
            }

            foreach ($properties as $property) {
                if (null === $subscriptions = $this->getSubscriptions(key: $class.'::'.$property)) {
                    continue;
                }

                yield from $this->processValidSubscriptions($subscriptions, $entityChangeSet, $entity, $action, $processedSubscriptionIds);
            }
        } while (false !== $class = get_parent_class($class));
    }

    /**
     * @param array<string, array{mixed, mixed}|PersistentCollection<array-key, object>> $entityChangeSet
     * @param array<string, true>                                                        $processedSubscriptionIds
     *
     * @return iterable<int, PurgeRoute>
     */
    private function processValidSubscriptions(Subscriptions $subscriptions, array $entityChangeSet, object $entity, Action $action, array &$processedSubscriptionIds): iterable
    {
        foreach ($subscriptions as $index => $subscription) {
            // a subscription targeting several properties is registered under each of them,
            // it only needs to be processed once even if more of those properties changed
            $subscriptionId = $this->subscriptionIds[$subscriptions->key()][$index] ??= self::createSubscriptionId($subscription);
            if (isset($processedSubscriptionIds[$subscriptionId])) {
                continue;
            }
            $processedSubscriptionIds[$subscriptionId] = true;

            if (isset($subscription['actions']) && !\in_array($action, $subscription['actions'], true)) {
                continue;
            }

            if (isset($subscription['if'])) {
                $result = $this->getExpressionLanguage()->evaluate($subscription['if'], ['obj' => $entity]);
                if (!\is_bool($result)) {
                    throw new InvalidIfExpressionResultException($subscription['routeName'], $subscription['if'], $result);
                }

                if (!$result) {
                    continue;
                }
            }

            $routeParamConfigs = $subscription['routeParams'] ?? [];

            /** @var array<string, list<?scalar>> $routeParamValues */
            $routeParamValues = [];

            foreach ($routeParamConfigs as $param => $config) {
                /** @var ValuesResolverInterface<array<mixed>> $routeParamValueResolver */
                $routeParamValueResolver = $this->routeParamValueResolverLocator->get($config['type']);
                $routeParamValues[$param] = $routeParamValueResolver->resolve($config['values'], $entity);

                if (!($config['optional'] ?? false)) {
                    $routeParamValues[$param] = array_values(
                        array_filter(
                            $routeParamValues[$param],
                            static fn (mixed $value): bool => null !== $value,
                        ),
                    );
                }
            }

            foreach ($this->processRouteParamValues($routeParamValues, $routeParamConfigs, $entityChangeSet) as $routeParams) {
                yield new PurgeRoute(
                    name: $subscription['routeName'],
                    params: $routeParams,
                );
            }
        }
    }

    /**
     * @param array<string, list<?scalar>>                                               $routeParamValues
     * @param array<string, array{type: string, values: list<mixed>, optional?: true}>   $routeParamConfigs
     * @param array<string, array{mixed, mixed}|PersistentCollection<array-key, object>> $entityChangeSet
     *
     * @return list<array<string, ?scalar>>
     */
    protected function processRouteParamValues(array $routeParamValues, array $routeParamConfigs, array $entityChangeSet): array
    {
        if (array_any($routeParamValues, static fn (array $value): bool => [] === $value)) {
            return []; // skip entire subscription if a certain param value is missing
        }

        return $this->getCartesianProduct($routeParamValues);
    }

    /**
     * @param array<string, list<?scalar>> $input
     *
     * @return list<array<string, ?scalar>>
     */
    protected function getCartesianProduct(array $input): array
    {
        $input = array_filter($input);
        $result = [[]];

        foreach ($input as $key => $values) {
            $append = [];

            foreach ($result as $product) {
                foreach ($values as $item) {
                    $product[$key] = $item;
                    $append[] = $product;
                }
            }

            $result = $append;
        }

        return $result;
    }

    /**
     * The copies of a subscription registered under several properties are equal arrays without any identity,
     * so they are matched by value. serialize() covers nested route params and enums and keeps value types
     * apart ('1' vs 1), and xxh128 turns the result into a short key: no cryptographic strength is needed here,
     * it's the fastest algorithm available, and with 128 bits a collision, which would silently skip a purge,
     * is not a practical concern.
     *
     * @param array<string, mixed> $subscription
     */
    private static function createSubscriptionId(array $subscription): string
    {
        return hash('xxh128', serialize($subscription));
    }

    /**
     * @param non-empty-string $key
     */
    private function getSubscriptions(string $key): ?Subscriptions
    {
        $this->configuration ??= $this->configurationLoader->load();

        return $this->configuration->has($key) ? $this->configuration->get($key) : null;
    }

    private function getExpressionLanguage(): ExpressionLanguage
    {
        return $this->expressionLanguage
            ?? throw new LogicException('You cannot use expressions because the Symfony ExpressionLanguage component is not installed. Try running "composer require symfony/expression-language".');
    }
}
