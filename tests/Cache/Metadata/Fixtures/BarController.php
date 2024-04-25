<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Cache\Metadata\Fixtures;

use Sofascore\PurgatoryBundle2\Attribute\PurgeOn;

class BarController
{
    #[PurgeOn('foo', route: 'nonexistent_route')]
    public function fooAction(): void
    {
    }
}
