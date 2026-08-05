<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Cache\Subscription\Fixtures;

class DummyEntity
{
    public function getData(): int
    {
        return 1;
    }
}
