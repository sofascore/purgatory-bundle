<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Cache\ControllerMetadata\Fixtures;

use Sofascore\PurgatoryBundle2\Attribute\PurgeOn;

#[PurgeOn('foo')]
class BazController
{
    public function __invoke()
    {
    }

    #[PurgeOn('bar')]
    public function barAction()
    {
    }
}
