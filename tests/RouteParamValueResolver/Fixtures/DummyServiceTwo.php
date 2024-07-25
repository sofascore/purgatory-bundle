<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\RouteParamValueResolver\Fixtures;

class DummyServiceTwo
{
    public function getValueToPurge(Foo $foo): int
    {
        return ($foo->id ?? 0) + 100;
    }

    public function getArrayValueToPurge(Foo $foo): array
    {
        return [$foo->id ?? 0, 1, 2, 3];
    }
}
