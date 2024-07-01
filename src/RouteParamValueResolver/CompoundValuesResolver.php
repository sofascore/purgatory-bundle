<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\RouteParamValueResolver;

use Psr\Container\ContainerInterface;
use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\CompoundValues;
use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\ValuesInterface;

/**
 * @implements ValuesResolverInterface<non-empty-list<array{type: class-string<ValuesInterface>, values: list<mixed>}>>
 */
final class CompoundValuesResolver implements ValuesResolverInterface
{
    public function __construct(
        private readonly ContainerInterface $routeParamValueResolverLocator,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public static function for(): string
    {
        return CompoundValues::class;
    }

    /**
     * {@inheritDoc}
     */
    public function resolve(array $unresolvedValues, object $entity): array
    {
        $resolvedRouteParameters = [];

        foreach ($unresolvedValues as $config) {
            /** @var ValuesResolverInterface<array<mixed>> $routeParamValueResolver */
            $routeParamValueResolver = $this->routeParamValueResolverLocator->get($config['type']);
            $resolvedRouteParameters = [...$resolvedRouteParameters, ...$routeParamValueResolver->resolve($config['values'], $entity)];
        }

        return $resolvedRouteParameters;
    }
}
