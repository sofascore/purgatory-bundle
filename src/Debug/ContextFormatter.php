<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Debug;

/**
 * @internal Shared by `purgatory:debug` and the profiler
 */
final class ContextFormatter
{
    /**
     * Compact JSON with enums written as `Class::Case`, or `var_export` on one line for what JSON cannot
     * hold, such as INF, NAN or invalid UTF-8.
     *
     * @param array<array-key, mixed> $context
     */
    public function format(array $context): string
    {
        array_walk_recursive($context, static function (mixed &$value): void {
            if ($value instanceof \UnitEnum) {
                $value = $value::class.'::'.$value->name;
            }
        });

        try {
            return json_encode($context, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $export = var_export($context, true);

            return preg_replace('/\n\s*/', ' ', $export) ?? $export;
        }
    }
}
