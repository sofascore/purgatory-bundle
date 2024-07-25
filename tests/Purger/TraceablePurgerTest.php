<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Purger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\DataCollector\PurgatoryDataCollector;
use Sofascore\PurgatoryBundle\Purger\PurgerInterface;
use Sofascore\PurgatoryBundle\Purger\TraceablePurger;

#[CoversClass(PurgatoryDataCollector::class)]
#[CoversClass(TraceablePurger::class)]
final class TraceablePurgerTest extends TestCase
{
    public function testPurge(): void
    {
        $array = ['http://localhost/foo', 'http://localhost/bar'];
        $generator = (static function () {
            yield 'http://localhost/baz';
            yield 'http://localhost/qux';
        })();

        $expected = [
            ['http://localhost/foo', 'http://localhost/bar'],
            ['http://localhost/baz', 'http://localhost/qux'],
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

        self::assertSame(['http://localhost/foo', 'http://localhost/bar'], $purges[0]['urls']);
        self::assertIsFloat($purges[0]['time']);
        self::assertSame(['http://localhost/baz', 'http://localhost/qux'], $purges[1]['urls']);
        self::assertIsFloat($purges[1]['time']);

        self::assertSame(4, $dataCollector->getTotalUrls());
        self::assertSame($purges[0]['time'] + $purges[1]['time'], $dataCollector->getTotalTime());
        self::assertSame('symfony', $dataCollector->getPurgerName());
        self::assertSame('foo', $dataCollector->getAsyncTransport());
    }
}
