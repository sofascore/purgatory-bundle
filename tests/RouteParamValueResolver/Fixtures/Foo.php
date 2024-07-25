<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\RouteParamValueResolver\Fixtures;

class Foo
{
    public ?int $id = null;

    public ?Foo $child = null;
}
