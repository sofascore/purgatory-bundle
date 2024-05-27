<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Cache\Metadata\Fixtures;

use Sofascore\PurgatoryBundle2\Attribute\PurgeOn;

class FooController
{
    #[PurgeOn('bar')]
    public function barAction()
    {
    }

    #[PurgeOn('baz1')]
    #[PurgeOn('baz2')]
    public function bazAction()
    {
    }
}
