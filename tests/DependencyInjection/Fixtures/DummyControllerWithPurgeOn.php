<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\DependencyInjection\Fixtures;

use Sofascore\PurgatoryBundle\Attribute\PurgeOn;

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
