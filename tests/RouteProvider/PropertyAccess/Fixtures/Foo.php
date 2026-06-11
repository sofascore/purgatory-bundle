<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\RouteProvider\PropertyAccess\Fixtures;

use Doctrine\Common\Collections\Collection;

class Foo
{
    public function __construct(
        public readonly int $id,
        public readonly Collection $children,
        public readonly ?self $linked = null,
        public readonly ?Collection $nullableChildren = null,
    ) {
    }

    public function childrenArray(): array
    {
        return $this->children->toArray();
    }
}
