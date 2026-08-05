<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Fixtures;

/**
 * Holds closures declared in constant expressions so deepclone can serialize them.
 *
 * The closure literals live in this fixture rather than inline in the tests because
 * constant-expression closures only parse on PHP 8.5+. Keeping them in a separately
 * autoloaded class means the file is loaded only when a PHP 8.5+ test
 * references it, so the test suites still parse on older PHP versions.
 */
final class ClosureIfHolder
{
    public const \Closure RETURNS_TRUE = static function (\stdClass $entity): bool {
        return true;
    };

    public const \Closure RETURNS_FALSE = static function (\stdClass $entity): bool {
        return false;
    };
}
