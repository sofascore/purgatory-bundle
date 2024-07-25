<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\RouteParamValueResolver\Fixtures;

class DummyServiceOne
{
    public function __invoke(Foo $foo): int
    {
        return ($foo->id ?? 0) + 1000;
    }
}
