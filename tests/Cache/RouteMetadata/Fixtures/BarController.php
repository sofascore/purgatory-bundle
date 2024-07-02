<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Cache\RouteMetadata\Fixtures;

use Sofascore\PurgatoryBundle2\Attribute\PurgeOn;

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
