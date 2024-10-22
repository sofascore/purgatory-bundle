<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Purger;

use PHPUnit\Framework\Attributes\CoversClass;
use Sofascore\PurgatoryBundle\Purger\PurgeRequest;
use Sofascore\PurgatoryBundle\Purger\SymfonyPurger;
use Sofascore\PurgatoryBundle\RouteProvider\PurgeRoute;
use Sofascore\PurgatoryBundle\Tests\Functional\AbstractKernelTestCase;
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
            ->with('http://localhost:80/foo');

        $purger = new SymfonyPurger($store);
        $purger->purge([
            new PurgeRequest('http://localhost:80/foo', new PurgeRoute('route_foo', [], [])),
        ]);
    }

    public function testPurgeWithHttpCache(): void
    {
        self::bootKernel(['test_case' => 'SymfonyPurger', 'config' => 'app_config.yaml']);

        /** @var HttpCache $kernel */
        $kernel = self::getContainer()->get('http_cache');

        self::assertSame('1', $kernel->handle(Request::create('http://localhost/'))->getContent());
        self::assertSame('1', $kernel->handle(Request::create('http://localhost/'))->getContent());

        self::getContainer()->get('sofascore.purgatory.purger.symfony')->purge([
            new PurgeRequest('http://localhost/', new PurgeRoute('route_name', [], [])),
        ]);

        self::assertSame('2', $kernel->handle(Request::create('http://localhost/'))->getContent());
    }
}
