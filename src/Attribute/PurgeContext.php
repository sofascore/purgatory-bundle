<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Attribute;

/**
 * Used on controller classes and methods to define a context for every {@see PurgeOn} of the routes they handle.
 *
 * The context of a purge subscription is the class context, merged with the method context and then with the context
 * of the {@see PurgeOn}. On every merge, a nested array with keys is merged key by key, lists are appended without
 * duplicates, and any other value replaces the one before it.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final class PurgeContext
{
    /**
     * @param array<array-key, mixed> $context may only contain scalars, enums, nulls and arrays, but not INF or NAN
     */
    public function __construct(
        public readonly array $context,
    ) {
        self::validate($context);
    }

    /**
     * @internal
     *
     * @param array<array-key, mixed> $context
     */
    public static function validate(array $context): void
    {
        array_walk_recursive($context, static function (mixed $value): void {
            if (null !== $value && !\is_scalar($value) && !$value instanceof \UnitEnum) {
                throw new \ValueError(\sprintf('The context may only contain scalars, enums, nulls and arrays, got "%s".', get_debug_type($value)));
            }

            if (\is_float($value) && !is_finite($value)) {
                throw new \ValueError('The context may not contain INF or NAN.');
            }
        });
    }
}
