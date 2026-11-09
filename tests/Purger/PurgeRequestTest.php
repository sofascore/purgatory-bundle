<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Purger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Purger\PurgeRequest;
use Sofascore\PurgatoryBundle\RouteProvider\PurgeRoute;

#[CoversClass(PurgeRequest::class)]
#[CoversClass(PurgeRoute::class)]
final class PurgeRequestTest extends TestCase
{
    public function testContextsDefaultToTheContextOfTheRoute(): void
    {
        $purgeRequest = new PurgeRequest('http://localhost/foo', new PurgeRoute('route_foo', [], ['throttle' => 8]));

        self::assertSame([['throttle' => 8]], $purgeRequest->contexts);
    }

    public function testRouteWithoutContextGivesOneEmptyContext(): void
    {
        $purgeRequest = new PurgeRequest('http://localhost/foo', new PurgeRoute('route_foo', []));

        self::assertSame([[]], $purgeRequest->contexts);
    }

    public function testGivenContextsAreKept(): void
    {
        $purgeRequest = new PurgeRequest(
            'http://localhost/foo',
            new PurgeRoute('route_foo', [], ['throttle' => 8]),
            [['throttle' => 8], []],
        );

        self::assertSame([['throttle' => 8], []], $purgeRequest->contexts);
    }

    /**
     * A purge message queued by 2.0.x is unserialized without the constructor and without these properties.
     */
    public function testRequestSerializedBeforeContextsExistedGetsTheDefaults(): void
    {
        $route = \sprintf('O:%d:"%s":2:{s:4:"name";s:9:"route_foo";s:6:"params";a:0:{}}', \strlen(PurgeRoute::class), PurgeRoute::class);
        $request = \sprintf('O:%d:"%s":2:{s:3:"url";s:20:"http://localhost/foo";s:5:"route";%s}', \strlen(PurgeRequest::class), PurgeRequest::class, $route);

        $purgeRequest = unserialize($request);

        self::assertInstanceOf(PurgeRequest::class, $purgeRequest);
        self::assertSame([], $purgeRequest->route->context);
        self::assertSame([[]], $purgeRequest->contexts);
    }

    public function testContextsSurviveSerialization(): void
    {
        $purgeRequest = new PurgeRequest(
            'http://localhost/foo',
            new PurgeRoute('route_foo', [], ['throttle' => 8]),
            [['throttle' => 8], []],
        );

        self::assertEquals($purgeRequest, unserialize(serialize($purgeRequest)));
    }
}
