<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\DependencyInjection\Fixtures;

use Sofascore\PurgatoryBundle\Attribute\PurgeOn;

class DummyController
{
    #[PurgeOn('test')]
    public function methodWithPurgeOn(): void
    {
    }

    public function methodWithoutPurgeOn(): void
    {
    }

    #[PurgeOn('test')]
    public function anotherMethodWithPurgeOn(): void
    {
    }
}
