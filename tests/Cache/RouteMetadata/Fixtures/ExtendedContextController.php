<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Cache\RouteMetadata\Fixtures;

use Sofascore\PurgatoryBundle\Attribute\PurgeContext;
use Sofascore\PurgatoryBundle\Attribute\PurgeOn;

#[PurgePriority('low')]
class ExtendedContextController
{
    #[PurgeContext(['throttle' => 8])]
    #[PurgeOn('foo')]
    #[PurgeOn('bar', context: new PurgePriority('high'))]
    public function fooAction(): void
    {
    }

    #[PurgePriority('high')]
    #[PurgeOn('baz')]
    public function bazAction(): void
    {
    }
}
