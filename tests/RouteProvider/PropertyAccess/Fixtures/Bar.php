<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\RouteProvider\PropertyAccess\Fixtures;

class Bar
{
    public function __construct(
        public readonly int $id,
        private readonly ?string $privateProperty = 'value',
    ) {
    }
}
