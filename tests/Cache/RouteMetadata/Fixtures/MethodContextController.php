<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Cache\RouteMetadata\Fixtures;

use Sofascore\PurgatoryBundle\Attribute\PurgeContext;
use Sofascore\PurgatoryBundle\Attribute\PurgeOn;

class MethodContextController
{
    #[PurgeContext(['tags' => ['post']])]
    #[PurgeOn('foo')]
    #[PurgeOn('bar', context: ['tags' => ['comment'], 'throttle' => 8])]
    public function fooAction(): void
    {
    }

    #[PurgeOn('baz', context: ['throttle' => 8])]
    public function bazAction(): void
    {
    }
}
