<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\PurgeRouteGenerator;

use Sofascore\PurgatoryBundle2\Cache\Configuration\ConfigurationLoaderInterface;
use Sofascore\PurgatoryBundle2\Exception\LogicException;
use Sofascore\PurgatoryBundle2\Listener\Enum\Action;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * @internal
 */
abstract class AbstractEntityRouteGenerator implements PurgeRouteGeneratorInterface
{
    /**
     * @param array<string, array{mixed, mixed}> $entityChangeSet
     *
     * @return array<int, string>
     */
    abstract protected function getChangedProperties(object $entity, array $entityChangeSet): array;

    /**
     * @param array<string, array{mixed, mixed}> $entityChangeSet
     *
     * @return list<scalar>
     */
    abstract protected function getRouteParameterValues(object $entity, array $entityChangeSet, string $property): array;

    /**
     * @var array<class-string|non-falsy-string, list<array{routeName: string, routeParams: array<string, string|list<string>>, if: ?string}>>
     */
    private ?array $subscriptions = null;

    public function __construct(
        private readonly ConfigurationLoaderInterface $configurationLoader,
        protected readonly PropertyAccessorInterface $propertyAccessor,
        private readonly ?ExpressionLanguage $expressionLanguage,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    final public function getRoutesToPurge(Action $action, object $entity, array $entityChangeSet): iterable
    {
        $class = $entity::class;
        $properties = $this->getChangedProperties($entity, $entityChangeSet);

        do {
            if (null !== $subscriptions = $this->getSubscriptions(key: $class)) {
                yield from $this->processValidSubscriptions($subscriptions, $entityChangeSet, $entity);
            }

            foreach ($properties as $property) {
                if (null === $subscriptions = $this->getSubscriptions(key: $class.'::'.$property)) {
                    continue;
                }

                yield from $this->processValidSubscriptions($subscriptions, $entityChangeSet, $entity);
            }
        } while (false !== $class = get_parent_class($class));
    }

    /**
     * @param list<array{routeName: string, routeParams: array<string, string|list<string>>, if: ?string}> $subscriptions
     * @param array<string, array{mixed, mixed}>                                                           $entityChangeSet
     *
     * @return iterable<int, array{routeName: string, routeParams: array<string, scalar>}>
     */
    private function processValidSubscriptions(array $subscriptions, array $entityChangeSet, object $entity): iterable
    {
        foreach ($subscriptions as $subscription) {
            if (null !== $subscription['if'] && false === $this->getExpressionLanguage()->evaluate($subscription['if'], ['obj' => $entity])) {
                continue;
            }

            /** @var array<string, list<scalar>> $resolvedRouteParameters */
            $resolvedRouteParameters = [];

            foreach ($subscription['routeParams'] as $param => $values) {
                $resolvedRouteParameters[$param] = [];

                foreach ((array) $values as $value) {
                    // const value
                    if ('@' === $value[0]) {
                        $resolvedRouteParameters[$param][] = substr($value, 1);
                        continue;
                    }

                    array_push(
                        $resolvedRouteParameters[$param],
                        ...$this->getRouteParameterValues($entity, $entityChangeSet, $value),
                    );
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
     * @param array<string, list<scalar>> $input
     *
     * @return list<array<string, scalar>>
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
     * @return list<array{routeName: string, routeParams: array<string, string|list<string>>, if: ?string}>
     */
    private function getSubscriptions(string $key): ?array
    {
        $this->subscriptions ??= $this->configurationLoader->load();

        return $this->subscriptions[$key] ?? null;
    }

    private function getExpressionLanguage(): ExpressionLanguage
    {
        return $this->expressionLanguage
            ?? throw new LogicException('Unable to use expressions as the Symfony ExpressionLanguage component is not installed. Try running "composer require symfony/expression-language".');
    }
}
