<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Purger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle2\Purger\SymfonyPurger;
use Symfony\Component\HttpKernel\HttpCache\StoreInterface;

#[CoversClass(SymfonyPurger::class)]
final class SymfonyPurgerTest extends TestCase
{
    public function testPurge(): void
    {
        $store = $this->createMock(StoreInterface::class);
        $store->expects(self::once())
            ->method('purge')
            ->with('localhost:80/foo');

        $purger = new SymfonyPurger($store, host: 'localhost:80');
        $purger->purge(['/foo']);
    }
}
