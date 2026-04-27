<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Cache\RouteMetadata\Fixtures;

use Sofascore\PurgatoryBundle\Attribute\PurgeOn;

class FooController
{
    #[PurgeOn('bar')]
    public function barAction(): void
    {
    }

    #[PurgeOn('baz1')]
    #[PurgeOn('baz2')]
    public function bazAction(): void
    {
    }
}
