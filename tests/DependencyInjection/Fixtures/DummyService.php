<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\DependencyInjection\Fixtures;

use Sofascore\PurgatoryBundle2\Attribute\PurgeOn;

class DummyService
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
