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

        self::assertSame(['/foo', '/bar', '/baz'], $purger->getPurgedUrls());
    }

    public static function urlsProvider(): iterable
    {
        yield 'array' => [
            ['/foo', '/bar', '/baz'],
        ];

        yield 'ArrayObject' => [
            new \ArrayObject(['/foo', '/bar', '/baz']),
        ];

        yield 'ArrayIterator' => [
            new \ArrayIterator(['/foo', '/bar', '/baz']),
        ];

        yield 'Generator' => [
            (static function () {
                yield '/foo';
                yield '/bar';
                yield '/baz';
            })(),
        ];
    }
}
