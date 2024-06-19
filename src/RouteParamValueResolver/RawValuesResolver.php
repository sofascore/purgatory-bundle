<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\RouteParamValueResolver;

use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\RawValues;

/**
 * @implements ValuesResolverInterface<?scalar>
 */
final class RawValuesResolver implements ValuesResolverInterface
{
    /**
     * {@inheritDoc}
     */
    public static function for(): string
    {
        return RawValues::class;
    }

    /**
     * {@inheritDoc}
     */
    public function resolve(array $unresolvedValues, object $entity): array
    {
        return $unresolvedValues;
    }
}
