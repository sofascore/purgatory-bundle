<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Cache\RouteMetadata\Fixtures;

use Sofascore\PurgatoryBundle\Attribute\PurgeContext;
use Sofascore\PurgatoryBundle\Attribute\PurgeOn;

class TwoMethodContextsController
{
    #[PurgeContext(['throttle' => 8])]
    #[PurgePriority('low')]
    #[PurgeOn('foo')]
    public function fooAction(): void
    {
    }
}
