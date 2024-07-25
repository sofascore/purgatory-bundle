<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Cache\RouteMetadata\Fixtures;

use Sofascore\PurgatoryBundle\Attribute\PurgeOn;

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
