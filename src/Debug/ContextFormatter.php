<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Debug;

/**
 * @internal
 */
final class ContextFormatter
{
    private const JSON_FLAGS = \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION | \JSON_INVALID_UTF8_SUBSTITUTE | \JSON_THROW_ON_ERROR;

    /**
     * Compact JSON, with enums written unquoted as `Class::Case`.
     *
     * @param array<array-key, mixed> $context
     */
    public function format(array $context): string
    {
        return self::formatValue($context);
    }

    private static function formatValue(mixed $value): string
    {
        if ($value instanceof \UnitEnum) {
            return $value::class.'::'.$value->name;
        }

        if (!\is_array($value)) {
            return json_encode($value, self::JSON_FLAGS);
        }

        if (array_is_list($value)) {
            return '['.implode(',', array_map(self::formatValue(...), $value)).']';
        }

        $pairs = [];
        foreach ($value as $key => $item) {
            $pairs[] = json_encode((string) $key, self::JSON_FLAGS).':'.self::formatValue($item);
        }

        return '{'.implode(',', $pairs).'}';
    }
}
