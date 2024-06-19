<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\RouteParamValueResolver;

use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\EnumValues;
use Sofascore\PurgatoryBundle2\Exception\InvalidArgumentException;

/**
 * @implements ValuesResolverInterface<\BackedEnum>
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
        $enumFqcn = $unresolvedValues[0] ?? throw new InvalidArgumentException('The list must contain exactly one enum class.');

        return array_map(
            static fn (\BackedEnum $case): int|string => $case->value,
            $enumFqcn::cases(),
        );
    }
}
