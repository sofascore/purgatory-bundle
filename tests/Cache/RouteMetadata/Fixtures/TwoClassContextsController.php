<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Cache\RouteMetadata\Fixtures;

use Sofascore\PurgatoryBundle\Attribute\PurgeContext;
use Sofascore\PurgatoryBundle\Attribute\PurgeOn;

#[PurgeContext(['throttle' => 8])]
#[PurgePriority('low')]
class TwoClassContextsController
{
    #[PurgeOn('foo')]
    public function fooAction(): void
    {
    }
}
