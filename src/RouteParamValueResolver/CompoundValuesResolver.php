<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\RouteParamValueResolver;

use Psr\Container\ContainerInterface;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\CompoundValues;

/**
 * @implements ValuesResolverInterface<non-empty-list<array{type: string, values: list<mixed>}>>
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
        return CompoundValues::type();
    }

    /**
     * {@inheritDoc}
     */
    public function resolve(array $unresolvedValues, object $entity): array
    {
        $values = [];

        foreach ($unresolvedValues as $config) {
            /** @var ValuesResolverInterface<array<mixed>> $routeParamValueResolver */
            $routeParamValueResolver = $this->routeParamValueResolverLocator->get($config['type']);
            $values = [...$values, ...$routeParamValueResolver->resolve($config['values'], $entity)];
        }

        return $values;
    }
}
