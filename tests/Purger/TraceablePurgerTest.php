<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Purger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle2\DataCollector\PurgatoryDataCollector;
use Sofascore\PurgatoryBundle2\Purger\PurgerInterface;
use Sofascore\PurgatoryBundle2\Purger\TraceablePurger;

#[CoversClass(PurgatoryDataCollector::class)]
#[CoversClass(TraceablePurger::class)]
final class TraceablePurgerTest extends TestCase
{
    public function testPurge(): void
    {
        $array = ['/foo', '/bar'];
        $generator = (static function () {
            yield '/baz';
            yield '/qux';
        })();

        $expected = [
            ['/foo', '/bar'],
            ['/baz', '/qux'],
        ];

        $innerPurger = $this->createMock(PurgerInterface::class);
        $innerPurger->expects(self::exactly(2))->method('purge')
            ->willReturnCallback(static function (array $urls) use (&$expected) {
                self::assertSame(array_shift($expected), $urls);
            });

        $purger = new TraceablePurger($innerPurger, $dataCollector = new PurgatoryDataCollector('symfony', 'foo'));

        $purger->purge($array);
        $purger->purge($generator);

        self::assertCount(2, $purges = $dataCollector->getPurges());

        self::assertSame(['/foo', '/bar'], $purges[0]['urls']);
        self::assertIsFloat($purges[0]['time']);
        self::assertSame(['/baz', '/qux'], $purges[1]['urls']);
        self::assertIsFloat($purges[1]['time']);

        self::assertSame(4, $dataCollector->getTotalUrls());
        self::assertSame($purges[0]['time'] + $purges[1]['time'], $dataCollector->getTotalTime());
        self::assertSame('symfony', $dataCollector->getPurgerName());
        self::assertSame('foo', $dataCollector->getAsyncTransport());
    }
}
