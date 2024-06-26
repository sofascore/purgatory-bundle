<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\RouteProvider\PropertyAccess\Fixtures;

use Doctrine\Common\Collections\Collection;

class Foo
{
    public function __construct(
        public readonly int $id,
        public readonly Collection $children,
    ) {
    }
}
