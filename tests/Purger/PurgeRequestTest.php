<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Purger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Purger\PurgeRequest;
use Sofascore\PurgatoryBundle\RouteProvider\PurgeRoute;

#[CoversClass(PurgeRequest::class)]
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
}
