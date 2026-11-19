<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Cache\RouteMetadata\Fixtures;

use Sofascore\PurgatoryBundle\Attribute\PurgeContext;
use Sofascore\PurgatoryBundle\Attribute\PurgeOn;

#[PurgeContext(['varnish' => ['tags' => ['social'], 'ttl' => 60], 'throttle' => 0])]
#[PurgeOn('class_level', route: 'context_bar', context: ['throttle' => 30])]
class ContextController
{
    #[PurgeOn('foo')]
    public function fooAction(): void
    {
    }

    #[PurgeOn('bar')]
    #[PurgeOn('baz', context: ['varnish' => ['tags' => ['comment']], 'throttle' => 8])]
    #[PurgeContext(['varnish' => ['ttl' => 5], 'throttle' => null])]
    public function barAction(): void
    {
    }
}
