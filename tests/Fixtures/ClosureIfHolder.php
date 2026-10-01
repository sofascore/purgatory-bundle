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

    public const \Closure TAKES_SERVICE = static function (\stdClass $entity, \ArrayObject $service): bool {
        return \count($service) > 0;
    };

    public const \Closure TAKES_SCALAR = static function (\stdClass $entity, int $other): bool {
        return true;
    };

    public const \Closure VALUES_WITH_SERVICE = static function (object $entity, \ArrayObject $service): array {
        return $service->getArrayCopy();
    };

    public const \Closure VALUES_TAKES_SCALAR = static function (object $entity, int $limit): array {
        return [];
    };
}
