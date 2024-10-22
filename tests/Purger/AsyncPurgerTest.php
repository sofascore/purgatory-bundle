<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Purger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Purger\AsyncPurger;
use Sofascore\PurgatoryBundle\Purger\Messenger\PurgeMessage;
use Sofascore\PurgatoryBundle\Purger\PurgeRequest;
use Sofascore\PurgatoryBundle\RouteProvider\PurgeRoute;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(AsyncPurger::class)]
final class AsyncPurgerTest extends TestCase
{
    #[DataProvider('providePurgeRequests')]
    public function testPurgeWithoutBatchSize(?int $batchSize, iterable $purgeRequests, array $batches): void
    {
        $messageBus = $this->createMock(MessageBusInterface::class);

        $messageBus->expects(self::exactly(\count($batches)))->method('dispatch')
            ->willReturnCallback(static function (PurgeMessage $purgeMessage) use (&$batches) {
                self::assertSame(array_shift($batches), $purgeMessage->purgeRequests);

                return new Envelope($purgeMessage);
            })
        ;

        $asyncPurger = new AsyncPurger($messageBus, $batchSize);
        $asyncPurger->purge($purgeRequests);
    }

    public static function providePurgeRequests(): iterable
    {
        $array = [
            $foo = new PurgeRequest('http://localhost/foo', new PurgeRoute('route_foo', [])),
            $bar = new PurgeRequest('http://localhost/bar', new PurgeRoute('route_bar', [])),
            $baz = new PurgeRequest('http://localhost/baz', new PurgeRoute('route_baz', [])),
            $qux = new PurgeRequest('http://localhost/qux', new PurgeRoute('route_qux', [])),
            $corge = new PurgeRequest('http://localhost/corge', new PurgeRoute('route_corge', [])),
        ];

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
            [[$foo, $bar], [$baz, $qux], [$corge]],
        ];

        yield 'Generator' => [
            3,
            (static function () use ($array) {
                yield from $array;
            })(),
            [[$foo, $bar, $baz], [$qux, $corge]],
        ];
    }

    public function testPurgeWithNoPurgeRequests(): void
    {
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');

        $asyncPurger = new AsyncPurger($messageBus);
        $asyncPurger->purge([]);
    }
}
