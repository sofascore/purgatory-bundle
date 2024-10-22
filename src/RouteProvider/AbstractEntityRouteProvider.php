<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\RouteProvider;

use Psr\Container\ContainerInterface;
use Sofascore\PurgatoryBundle\Cache\Configuration\Configuration;
use Sofascore\PurgatoryBundle\Cache\Configuration\ConfigurationLoaderInterface;
use Sofascore\PurgatoryBundle\Cache\Configuration\Subscriptions;
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
     * @param array<string, array{mixed, mixed}> $entityChangeSet
     *
     * @return array<int, string>
     */
    abstract protected function getChangedProperties(object $entity, array $entityChangeSet): array;

    private ?Configuration $configuration = null;

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

        do {
            if (null !== $subscriptions = $this->getSubscriptions(key: $class)) {
                yield from $this->processValidSubscriptions($subscriptions, $entityChangeSet, $entity, $action);
            }

            foreach ($properties as $property) {
                if (null === $subscriptions = $this->getSubscriptions(key: $class.'::'.$property)) {
                    continue;
                }

                yield from $this->processValidSubscriptions($subscriptions, $entityChangeSet, $entity, $action);
            }
        } while (false !== $class = get_parent_class($class));
    }

    /**
     * @param array<string, array{mixed, mixed}> $entityChangeSet
     *
     * @return iterable<int, PurgeRoute>
     */
    private function processValidSubscriptions(Subscriptions $subscriptions, array $entityChangeSet, object $entity, Action $action): iterable
    {
        foreach ($subscriptions as $subscription) {
            if (isset($subscription['actions']) && !\in_array($action, $subscription['actions'], true)) {
                continue;
            }

            if (isset($subscription['if']) && false === $this->getExpressionLanguage()->evaluate($subscription['if'], ['obj' => $entity])) {
                continue;
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
                    context: $subscription['context'] ?? [],
                );
            }
        }
    }

    /**
     * @param array<string, list<?scalar>>                                             $routeParamValues
     * @param array<string, array{type: string, values: list<mixed>, optional?: true}> $routeParamConfigs
     * @param array<string, array{mixed, mixed}>                                       $entityChangeSet
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
