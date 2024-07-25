<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Cache\RouteMetadata\Fixtures;

use Sofascore\PurgatoryBundle\Attribute\PurgeOn;

class BarController
{
    #[PurgeOn('foo', route: 'foo_bar1')]
    public function fooAction()
    {
    }

    #[PurgeOn('foo', route: ['foo_baz1', 'foo_baz3'])]
    public function bazAction()
    {
    }
}
