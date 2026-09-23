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
        private readonly ?string $privateProperty = 'value',
    ) {
    }

    public function childrenArray(): array
    {
        return $this->children->toArray();
    }
}
