<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\DependencyInjection\Fixtures;

use Sofascore\PurgatoryBundle2\Attribute\PurgeOn;

class DummyController
{
    #[PurgeOn('test')]
    public function methodWithPurgeOn()
    {
    }

    public function methodWithoutPurgeOn()
    {
    }

    #[PurgeOn('test')]
    public function anotherMethodWithPurgeOn()
    {
    }
}
