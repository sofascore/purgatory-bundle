<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\DependencyInjection\Fixtures;

class DummyNonPublicMethodService
{
    protected function foo(): void
    {
    }
}
