<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Purger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Purger\ChainPurger;
use Sofascore\PurgatoryBundle\Purger\InMemoryPurger;
use Sofascore\PurgatoryBundle\Purger\PurgeRequest;
use Sofascore\PurgatoryBundle\Purger\PurgerInterface;
use Sofascore\PurgatoryBundle\RouteProvider\PurgeRoute;

#[CoversClass(ChainPurger::class)]
final class ChainPurgerTest extends TestCase
{
    #[DataProvider('providePurgeRequests')]
    public function testPurge(iterable $purgeRequests): void
    {
        $firstPurger = new InMemoryPurger();
        $secondPurger = new InMemoryPurger();

        $purger = new ChainPurger([$firstPurger, $secondPurger]);
        $purger->purge($purgeRequests);

        self::assertSame(['http://localhost/foo', 'http://localhost/bar', 'http://localhost/baz'], $firstPurger->getPurgedUrls());
        self::assertSame(['http://localhost/foo', 'http://localhost/bar', 'http://localhost/baz'], $secondPurger->getPurgedUrls());
    }

    public static function providePurgeRequests(): iterable
    {
        $array = [
            new PurgeRequest('http://localhost/foo', new PurgeRoute('route_foo', [])),
            new PurgeRequest('http://localhost/bar', new PurgeRoute('route_bar', [])),
            new PurgeRequest('http://localhost/baz', new PurgeRoute('route_baz', [])),
        ];

        yield 'array' => [
            $array,
        ];

        yield 'ArrayObject' => [
            new \ArrayObject($array),
        ];

        yield 'ArrayIterator' => [
            new \ArrayIterator($array),
        ];

        yield 'Generator' => [
            (static function () use ($array) {
                yield from $array;
            })(),
        ];
    }

    public function testPurgersAreCalledInOrder(): void
    {
        $calls = [];

        $firstPurger = $this->createMock(PurgerInterface::class);
        $firstPurger->expects(self::once())->method('purge')
            ->willReturnCallback(static function () use (&$calls): void {
                $calls[] = 'first';
            });

        $secondPurger = $this->createMock(PurgerInterface::class);
        $secondPurger->expects(self::once())->method('purge')
            ->willReturnCallback(static function () use (&$calls): void {
                $calls[] = 'second';
            });

        (new ChainPurger([$firstPurger, $secondPurger]))->purge([
            new PurgeRequest('http://localhost/foo', new PurgeRoute('route_foo', [])),
        ]);

        self::assertSame(['first', 'second'], $calls);
    }
}
