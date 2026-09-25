<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Fixtures;

final class IfCallables
{
    public static function isTrue(\stdClass $entity): bool
    {
        return true;
    }

    public static function isFalse(\stdClass $entity): bool
    {
        return false;
    }

    public static function returnsInt(\stdClass $entity): int
    {
        return 1;
    }

    public static function takesTwo(\stdClass $entity, int $other): bool
    {
        return true;
    }

    public static function takesWrongType(\DateTimeInterface $entity): bool
    {
        return true;
    }

    public function instanceMethod(\stdClass $entity): bool
    {
        return true;
    }
}
