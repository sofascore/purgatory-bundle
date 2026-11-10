<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\DataCollector;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\DataCollector\PurgatoryDataCollector;
use Sofascore\PurgatoryBundle\Debug\ContextFormatter;
use Sofascore\PurgatoryBundle\Purger\PurgeRequest;
use Sofascore\PurgatoryBundle\RouteProvider\PurgeRoute;

#[CoversClass(PurgatoryDataCollector::class)]
final class PurgatoryDataCollectorTest extends TestCase
{
    public function testContextsAreCollectedPerRequest(): void
    {
        $collector = new PurgatoryDataCollector('symfony', null, new ContextFormatter());

        $collector->collectPurgeRequests([
            new PurgeRequest('http://localhost/foo', new PurgeRoute('foo', [], ['throttle' => 8]), [['throttle' => 8], []]),
            new PurgeRequest('http://localhost/bar', new PurgeRoute('bar', [])),
        ], 0.1);

        self::assertTrue($collector->hasContexts());
        self::assertSame(['[{"throttle":8},[]]', ''], $collector->getPurges()[0]['contexts'] ?? null);
    }

    public function testNoContextsWhenEverySubscriptionHasNone(): void
    {
        $collector = new PurgatoryDataCollector('symfony', null, new ContextFormatter());

        $collector->collectPurgeRequests([
            new PurgeRequest('http://localhost/foo', new PurgeRoute('foo', [])),
        ], 0.1);

        self::assertFalse($collector->hasContexts());
    }

    public function testResetClearsContexts(): void
    {
        $collector = new PurgatoryDataCollector('symfony', null, new ContextFormatter());
        $collector->collectPurgeRequests([
            new PurgeRequest('http://localhost/foo', new PurgeRoute('foo', [], ['throttle' => 8])),
        ], 0.1);
        self::assertTrue($collector->hasContexts());

        $collector->reset();

        self::assertFalse($collector->hasContexts());
        self::assertSame([], $collector->getPurges());
    }

    /**
     * Profiles are stored serialized, so one stored by an earlier version has no context data.
     */
    public function testProfileStoredBeforeContextsHasNone(): void
    {
        $collector = new PurgatoryDataCollector('symfony', null, new ContextFormatter());
        $data = new \ReflectionProperty(PurgatoryDataCollector::class, 'data');
        $data->setValue($collector, [
            'purger_name' => 'symfony',
            'async_transport' => null,
            'purges' => [['requests' => [new PurgeRequest('http://localhost/foo', new PurgeRoute('foo', []))], 'time' => 0.1]],
        ]);

        self::assertFalse($collector->hasContexts());
    }
}
