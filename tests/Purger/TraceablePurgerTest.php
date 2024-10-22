<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Purger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\DataCollector\PurgatoryDataCollector;
use Sofascore\PurgatoryBundle\Purger\PurgeRequest;
use Sofascore\PurgatoryBundle\Purger\PurgerInterface;
use Sofascore\PurgatoryBundle\Purger\TraceablePurger;
use Sofascore\PurgatoryBundle\RouteProvider\PurgeRoute;

#[CoversClass(PurgatoryDataCollector::class)]
#[CoversClass(TraceablePurger::class)]
final class TraceablePurgerTest extends TestCase
{
    public function testPurge(): void
    {
        $array = [
            new PurgeRequest('http://localhost/foo', new PurgeRoute('route_foo', [], [])),
            new PurgeRequest('http://localhost/bar', new PurgeRoute('route_bar', [], [])),
        ];
        $generator = (static function (): \Generator {
            yield new PurgeRequest('http://localhost/baz', new PurgeRoute('route_baz', [], []));
            yield new PurgeRequest('http://localhost/qux', new PurgeRoute('route_qux', [], []));
        })();

        $expected = [
            ['http://localhost/foo', 'http://localhost/bar'],
            ['http://localhost/baz', 'http://localhost/qux'],
        ];

        $innerPurger = $this->createMock(PurgerInterface::class);
        $innerPurger->expects(self::exactly(2))->method('purge')
            ->willReturnCallback(static function (array $purgeRequests) use (&$expected) {
                self::assertSame(
                    array_shift($expected),
                    array_map(static fn (PurgeRequest $purgeRequest): string => $purgeRequest->url, $purgeRequests),
                );
            });

        $purger = new TraceablePurger($innerPurger, $dataCollector = new PurgatoryDataCollector('symfony', 'foo'));

        $purger->purge($array);
        $purger->purge($generator);

        self::assertCount(2, $purges = $dataCollector->getPurges());

        self::assertSame(
            ['http://localhost/foo', 'http://localhost/bar'],
            array_map(static fn (PurgeRequest $purgeRequest): string => $purgeRequest->url, $purges[0]['requests']),
        );
        self::assertIsFloat($purges[0]['time']);
        self::assertSame(
            ['http://localhost/baz', 'http://localhost/qux'],
            array_map(static fn (PurgeRequest $purgeRequest): string => $purgeRequest->url, $purges[1]['requests']),
        );
        self::assertIsFloat($purges[1]['time']);

        self::assertSame(4, $dataCollector->getTotalRequests());
        self::assertSame($purges[0]['time'] + $purges[1]['time'], $dataCollector->getTotalTime());
        self::assertSame('symfony', $dataCollector->getPurgerName());
        self::assertSame('foo', $dataCollector->getAsyncTransport());

        $dataCollector->reset();

        self::assertSame([], $dataCollector->getPurges());
        self::assertSame(0, $dataCollector->getTotalRequests());
        self::assertSame(0.0, $dataCollector->getTotalTime());
        self::assertSame('symfony', $dataCollector->getPurgerName());
        self::assertSame('foo', $dataCollector->getAsyncTransport());
    }
}
