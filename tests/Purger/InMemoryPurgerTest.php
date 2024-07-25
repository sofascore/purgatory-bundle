<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Purger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle2\Purger\InMemoryPurger;

#[CoversClass(InMemoryPurger::class)]
final class InMemoryPurgerTest extends TestCase
{
    #[DataProvider('urlsProvider')]
    public function testPurge(iterable $urlsToPurge): void
    {
        $purger = new InMemoryPurger();
        $purger->purge($urlsToPurge);

        self::assertSame(['http://localhost/foo', 'http://localhost/bar', 'http://localhost/baz'], $purger->getPurgedUrls());

        $purger->reset();

        self::assertSame([], $purger->getPurgedUrls());
    }

    public static function urlsProvider(): iterable
    {
        $array = ['http://localhost/foo', 'http://localhost/bar', 'http://localhost/baz'];

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
}
