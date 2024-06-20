<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\DependencyInjection\Fixtures;

use Sofascore\PurgatoryBundle2\Attribute\PurgeOn;

#[PurgeOn('test')]
class DummyControllerWithPurgeOn
{
    public function __invoke()
    {
    }

    #[PurgeOn('test')]
    public function methodWithPurgeOn()
    {
    }
}
