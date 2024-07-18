<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Purger;

use PHPUnit\Framework\Attributes\CoversClass;
use Sofascore\PurgatoryBundle2\Purger\SymfonyPurger;
use Sofascore\PurgatoryBundle2\Tests\Functional\AbstractKernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpCache\HttpCache;
use Symfony\Component\HttpKernel\HttpCache\StoreInterface;

#[CoversClass(SymfonyPurger::class)]
final class SymfonyPurgerTest extends AbstractKernelTestCase
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

    public function testPurgeWithHttpCache(): void
    {
        self::bootKernel(['test_case' => 'SymfonyPurger', 'config' => 'app_config.yaml']);

        /** @var HttpCache $kernel */
        $kernel = self::getContainer()->get('http_cache');

        self::assertSame('1', $kernel->handle(Request::create('/'))->getContent());
        self::assertSame('1', $kernel->handle(Request::create('/'))->getContent());

        self::getContainer()->get('sofascore.purgatory2.purger.symfony')->purge(['/']);

        self::assertSame('2', $kernel->handle(Request::create('/'))->getContent());
    }
}
