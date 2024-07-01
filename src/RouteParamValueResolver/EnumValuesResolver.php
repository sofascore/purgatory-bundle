<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\RouteParamValueResolver;

use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\EnumValues;

/**
 * @implements ValuesResolverInterface<array{0: \BackedEnum}>
 */
final class EnumValuesResolver implements ValuesResolverInterface
{
    /**
     * {@inheritDoc}
     */
    public static function for(): string
    {
        return EnumValues::class;
    }

    /**
     * {@inheritDoc}
     */
    public function resolve(array $unresolvedValues, object $entity): array
    {
        /** @var class-string<\BackedEnum> $enumFqcn */
        $enumFqcn = $unresolvedValues[0];

        return array_map(
            static fn (\BackedEnum $case): int|string => $case->value,
            $enumFqcn::cases(),
        );
    }
}
