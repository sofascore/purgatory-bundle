<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Cache\Metadata;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle2\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle2\Attribute\Target\ForProperties;
use Sofascore\PurgatoryBundle2\Cache\Metadata\ControllerMetadata;
use Sofascore\PurgatoryBundle2\Cache\Metadata\ControllerMetadataProviderInterface;
use Sofascore\PurgatoryBundle2\Cache\Metadata\PurgeSubscription;
use Sofascore\PurgatoryBundle2\Cache\Metadata\PurgeSubscriptionProvider;
use Symfony\Component\Routing\Route;

#[CoversClass(PurgeSubscriptionProvider::class)]
final class PurgeSubscriptionProviderTest extends TestCase
{
    #[DataProvider('provideControllerMetadata')]
    public function testPropertySubscription(ControllerMetadata $controllerMetadata, array $expectedSubscriptions): void
    {
        $controllerMetadataProvider = $this->createMock(ControllerMetadataProviderInterface::class);
        $controllerMetadataProvider->method('provide')
            ->willReturnCallback(function () use ($controllerMetadata) {
                yield $controllerMetadata;
            });
        $propertySubscriptionProvider = new PurgeSubscriptionProvider($controllerMetadataProvider);

        /** @var PurgeSubscription[] $propertySubscriptions */
        $propertySubscriptions = [...$propertySubscriptionProvider->provide()];

        self::assertCount(\count($expectedSubscriptions), $propertySubscriptions);
        self::assertEquals($expectedSubscriptions, $propertySubscriptions);
    }

    public static function provideControllerMetadata(): iterable
    {
        $route = new Route('/foo');
        yield 'PurgeOn without target properties for route without params' => [
            'controllerMetadata' => new ControllerMetadata(
                routeName: 'foo',
                route: $route,
                purgeOn: new PurgeOn(
                    class: 'FooEntity',
                ),
            ),
            'expectedSubscriptions' => [
                new PurgeSubscription(
                    class: 'FooEntity',
                    property: null,
                    routeParams: [],
                    routeName: 'foo',
                    route: $route,
                    if: null,
                ),
            ],
        ];

        yield 'PurgeOn with multiple target properties for route without params' => [
            'controllerMetadata' => new ControllerMetadata(
                routeName: 'foo',
                route: $route,
                purgeOn: new PurgeOn(
                    class: 'FooEntity',
                    target: new ForProperties(['property1', 'property2']),
                ),
            ),
            'expectedSubscriptions' => [
                new PurgeSubscription(
                    class: 'FooEntity',
                    property: 'property1',
                    routeParams: [],
                    routeName: 'foo',
                    route: $route,
                    if: null,
                ),
                new PurgeSubscription(
                    class: 'FooEntity',
                    property: 'property2',
                    routeParams: [],
                    routeName: 'foo',
                    route: $route,
                    if: null,
                ),
            ],
        ];

        $route = new Route('/foo/{bar}');
        yield 'PurgeOn without target properties for route with params' => [
            'controllerMetadata' => new ControllerMetadata(
                routeName: 'foo',
                route: $route,
                purgeOn: new PurgeOn(
                    class: 'FooEntity',
                    routeParams: ['bar' => 'baz'],
                ),
            ),
            'expectedSubscriptions' => [
                new PurgeSubscription(
                    class: 'FooEntity',
                    property: null,
                    routeParams: ['bar' => 'baz'],
                    routeName: 'foo',
                    route: $route,
                    if: null,
                ),
            ],
        ];

        $route = new Route('/foo/{bar}/{baz}');
        yield 'PurgeOn with automatic route params resolving' => [
            'controllerMetadata' => new ControllerMetadata(
                routeName: 'foo',
                route: $route,
                purgeOn: new PurgeOn(
                    class: 'FooEntity',
                ),
            ),
            'expectedSubscriptions' => [
                new PurgeSubscription(
                    class: 'FooEntity',
                    property: null,
                    routeParams: ['bar' => 'bar', 'baz' => 'baz'],
                    routeName: 'foo',
                    route: $route,
                    if: null,
                ),
            ],
        ];

        yield 'PurgeOn with multiple target properties and automatic route params resolving' => [
            'controllerMetadata' => new ControllerMetadata(
                routeName: 'foo',
                route: $route,
                purgeOn: new PurgeOn(
                    class: 'FooEntity',
                    target: new ForProperties(['property1', 'property2']),
                ),
            ),
            'expectedSubscriptions' => [
                new PurgeSubscription(
                    class: 'FooEntity',
                    property: 'property1',
                    routeParams: ['bar' => 'bar', 'baz' => 'baz'],
                    routeName: 'foo',
                    route: $route,
                    if: null,
                ),
                new PurgeSubscription(
                    class: 'FooEntity',
                    property: 'property2',
                    routeParams: ['bar' => 'bar', 'baz' => 'baz'],
                    routeName: 'foo',
                    route: $route,
                    if: null,
                ),
            ],
        ];
    }
}
