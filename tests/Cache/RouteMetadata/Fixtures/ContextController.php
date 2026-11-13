<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Cache\RouteMetadata\Fixtures;

use Sofascore\PurgatoryBundle\Attribute\PurgeContext;
use Sofascore\PurgatoryBundle\Attribute\PurgeOn;

#[PurgeContext(['varnish' => ['tags' => ['social'], 'ttl' => 60]])]
#[PurgeOn('class_level', route: 'context_bar')]
class ContextController
{
    #[PurgeOn('foo')]
    public function fooAction(): void
    {
    }

    #[PurgeContext(['varnish' => ['tags' => ['post'], 'ttl' => 5]])]
    #[PurgeOn('bar')]
    #[PurgeOn('baz', context: ['varnish' => ['tags' => ['comment']], 'throttle' => 8])]
    public function barAction(): void
    {
    }
}
