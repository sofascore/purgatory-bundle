<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Purger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Purger\AsyncPurger;
use Sofascore\PurgatoryBundle\Purger\Messenger\PurgeMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(AsyncPurger::class)]
final class AsyncPurgerTest extends TestCase
{
    #[DataProvider('urlsProvider')]
    public function testPurgeWithoutBatchSize(?int $batchSize, iterable $urlsToPurge, array $batches): void
    {
        $messageBus = $this->createMock(MessageBusInterface::class);

        $messageBus->expects(self::exactly(\count($batches)))->method('dispatch')
            ->willReturnCallback(static function (PurgeMessage $purgeMessage) use (&$batches) {
                self::assertSame(array_shift($batches), $purgeMessage->urls);

                return new Envelope($purgeMessage);
            })
        ;

        $asyncPurger = new AsyncPurger($messageBus, $batchSize);
        $asyncPurger->purge($urlsToPurge);
    }

    public static function urlsProvider(): iterable
    {
        $array = ['http://localhost/foo', 'http://localhost/bar', 'http://localhost/baz', 'http://localhost/qux', 'http://localhost/corge'];

        yield 'array' => [
            null,
            $array,
            [$array],
        ];

        yield 'ArrayObject' => [
            null,
            new \ArrayObject($array),
            [$array],
        ];

        yield 'ArrayIterator' => [
            2,
            new \ArrayIterator($array),
            [['http://localhost/foo', 'http://localhost/bar'], ['http://localhost/baz', 'http://localhost/qux'], ['http://localhost/corge']],
        ];

        yield 'Generator' => [
            3,
            (static function () use ($array) {
                yield from $array;
            })(),
            [['http://localhost/foo', 'http://localhost/bar', 'http://localhost/baz'], ['http://localhost/qux', 'http://localhost/corge']],
        ];
    }

    public function testPurgeWithNoURLs(): void
    {
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');

        $asyncPurger = new AsyncPurger($messageBus);
        $asyncPurger->purge([]);
    }
}
