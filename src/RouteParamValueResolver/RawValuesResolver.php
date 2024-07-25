<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\RouteParamValueResolver;

use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\RawValues;

/**
 * @implements ValuesResolverInterface<non-empty-list<?scalar>>
 */
final class RawValuesResolver implements ValuesResolverInterface
{
    /**
     * {@inheritDoc}
     */
    public static function for(): string
    {
        return RawValues::type();
    }

    /**
     * {@inheritDoc}
     */
    public function resolve(array $unresolvedValues, object $entity): array
    {
        return $unresolvedValues;
    }
}
