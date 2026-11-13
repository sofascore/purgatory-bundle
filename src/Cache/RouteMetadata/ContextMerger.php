<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Cache\RouteMetadata;

/**
 * @internal Used during cache warmup
 */
final class ContextMerger
{
    /**
     * Merges the overrides into the context. Nested arrays with keys are merged key by key, lists are appended without
     * duplicates, and any other value of the overrides replaces the one in the context.
     *
     * @param array<array-key, mixed> $context
     * @param array<array-key, mixed> $overrides
     *
     * @return array<array-key, mixed>
     */
    public static function merge(array $context, array $overrides): array
    {
        if ([] === $context) {
            return $overrides;
        }

        if ([] === $overrides) {
            return $context;
        }

        if (array_is_list($context) && array_is_list($overrides)) {
            return self::append($context, $overrides);
        }

        foreach ($overrides as $key => $value) {
            $current = $context[$key] ?? null;

            $context[$key] = \is_array($current) && \is_array($value) ? self::merge($current, $value) : $value;
        }

        return $context;
    }

    /**
     * @param list<mixed> $list
     * @param list<mixed> $items
     *
     * @return list<mixed>
     */
    private static function append(array $list, array $items): array
    {
        $appended = [];

        foreach ([...$list, ...$items] as $item) {
            if (!\in_array($item, $appended, true)) {
                $appended[] = $item;
            }
        }

        return $appended;
    }
}
