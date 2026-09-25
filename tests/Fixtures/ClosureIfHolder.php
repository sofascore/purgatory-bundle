<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Fixtures;

/**
 * Holds closures declared in constant expressions so DeepClone can serialize them.
 *
 * Closures in constant expressions only parse on PHP 8.5+, so they live in this separately autoloaded
 * class instead of inline in the tests. The file is loaded only when a PHP 8.5+ test references it,
 * which keeps the test suite parseable on older PHP versions.
 */
final class ClosureIfHolder
{
    public const \Closure RETURNS_TRUE = static function (\stdClass $entity): bool {
        return true;
    };

    public const \Closure RETURNS_FALSE = static function (\stdClass $entity): bool {
        return false;
    };

    public const \Closure VALUES = static function (object $entity): array {
        return [7, 8];
    };
}
