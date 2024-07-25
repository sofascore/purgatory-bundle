<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Cache\PropertyResolver\Fixtures;

use Sofascore\PurgatoryBundle\Attribute\TargetedProperties;

class DummyEntity
{
    #[TargetedProperties('bar', 'baz')]
    public function getFoo()
    {
    }
}
