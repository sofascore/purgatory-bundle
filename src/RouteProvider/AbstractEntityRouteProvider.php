<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\RouteProvider;

use Psr\Container\ContainerInterface;
use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\ValuesInterface;
use Sofascore\PurgatoryBundle2\Cache\Configuration\ConfigurationLoaderInterface;
use Sofascore\PurgatoryBundle2\Exception\LogicException;
use Sofascore\PurgatoryBundle2\Listener\Enum\Action;
use Sofascore\PurgatoryBundle2\RouteParamValueResolver\ValuesResolverInterface;
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

    /**
     * @var array<class-string|non-falsy-string, list<array{
     *     routeName: string,
     *     routeParams?: array<string, array{type: class-string<ValuesInterface>, values: list<mixed>, optional?: true}>,
     *     if?: string,
     *     actions?: non-empty-list<Action>,
     * }>>
     */
    private ?array $subscriptions = null;

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
     * @param list<array{
     *     routeName: string,
     *     routeParams?: array<string, array{type: class-string<ValuesInterface>, values: list<mixed>, optional?: true}>,
     *     if?: string,
     *     actions?: non-empty-list<Action>,
     * }>                                        $subscriptions
     * @param array<string, array{mixed, mixed}> $entityChangeSet @TODO ovo treba iskoristiti
     *
     * @return iterable<int, array{routeName: string, routeParams: array<string, ?scalar>}>
     */
    private function processValidSubscriptions(array $subscriptions, array $entityChangeSet, object $entity, Action $action): iterable
    {
        foreach ($subscriptions as $subscription) {
            if (isset($subscription['actions']) && !\in_array($action, $subscription['actions'], true)) {
                continue;
            }

            if (isset($subscription['if']) && false === $this->getExpressionLanguage()->evaluate($subscription['if'], ['obj' => $entity])) {
                continue;
            }

            /** @var array<string, list<?scalar>> $resolvedRouteParameters */
            $resolvedRouteParameters = [];

            foreach ($subscription['routeParams'] ?? [] as $param => $config) {
                /** @var ValuesResolverInterface<array<mixed>> $routeParamValueResolver */
                $routeParamValueResolver = $this->routeParamValueResolverLocator->get($config['type']);
                $resolvedRouteParameters[$param] = $routeParamValueResolver->resolve($config['values'], $entity);

                if (!($config['optional'] ?? false)) {
                    $resolvedRouteParameters[$param] = array_values(
                        array_filter(
                            $resolvedRouteParameters[$param],
                            static fn (mixed $value): bool => null !== $value,
                        ),
                    );

                    if ([] === $resolvedRouteParameters[$param]) {
                        continue 2; // skip whole subscription
                    }
                }
            }

            foreach ($this->getCartesianProduct($resolvedRouteParameters) as $routeParams) {
                yield [
                    'routeName' => $subscription['routeName'],
                    'routeParams' => $routeParams,
                ];
            }
        }
    }

    /**
     * @param array<string, list<?scalar>> $input
     *
     * @return list<array<string, ?scalar>>
     */
    private function getCartesianProduct(array $input): array
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
     * @return ?list<array{
     *     routeName: string,
     *     routeParams?: array<string, array{type: class-string<ValuesInterface>, values: list<mixed>, optional?: true}>,
     *     if?: string,
     *     actions?: non-empty-list<Action>,
     * }>
     */
    private function getSubscriptions(string $key): ?array
    {
        $this->subscriptions ??= $this->configurationLoader->load();

        return $this->subscriptions[$key] ?? null;
    }

    private function getExpressionLanguage(): ExpressionLanguage
    {
        return $this->expressionLanguage
            ?? throw new LogicException('You cannot use expressions because the Symfony ExpressionLanguage component is not installed. Try running "composer require symfony/expression-language".');
    }
}
