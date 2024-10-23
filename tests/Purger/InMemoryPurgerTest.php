<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Purger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Purger\InMemoryPurger;
use Sofascore\PurgatoryBundle\Purger\PurgeRequest;
use Sofascore\PurgatoryBundle\RouteProvider\PurgeRoute;

#[CoversClass(InMemoryPurger::class)]
final class InMemoryPurgerTest extends TestCase
{
    #[DataProvider('providePurgeRequests')]
    public function testPurge(iterable $purgeRequests): void
    {
        $purger = new InMemoryPurger();
        $purger->purge($purgeRequests);

        self::assertSame(['http://localhost/foo', 'http://localhost/bar', 'http://localhost/baz'], $purger->getPurgedUrls());

        $purger->reset();

        self::assertContainsOnlyInstancesOf(PurgeRequest::class, $purger->getPurgedRequests());
        self::assertSame([], $purger->getPurgedUrls());
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
}
