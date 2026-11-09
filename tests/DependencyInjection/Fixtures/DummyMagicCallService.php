<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\DependencyInjection\Fixtures;

class DummyMagicCallService
{
    /**
     * @param list<mixed> $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        return null;
    }
}
