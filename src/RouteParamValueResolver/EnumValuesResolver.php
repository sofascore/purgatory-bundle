<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\RouteParamValueResolver;

use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\EnumValues;

/**
 * @implements ValuesResolverInterface<array{0: class-string<\BackedEnum>}>
 */
final class EnumValuesResolver implements ValuesResolverInterface
{
    /**
     * {@inheritDoc}
     */
    public static function for(): string
    {
        return EnumValues::type();
    }

    /**
     * {@inheritDoc}
     */
    public function resolve(array $unresolvedValues, object $entity): array
    {
        return array_map(
            static fn (\BackedEnum $case): int|string => $case->value,
            $unresolvedValues[0]::cases(),
        );
    }
}
