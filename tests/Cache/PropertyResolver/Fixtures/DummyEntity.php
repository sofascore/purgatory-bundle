<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Cache\PropertyResolver\Fixtures;

use Sofascore\PurgatoryBundle2\Attribute\TargetedProperties;

class DummyEntity
{
    #[TargetedProperties('bar', 'baz')]
    public function getFoo()
    {
    }
}
