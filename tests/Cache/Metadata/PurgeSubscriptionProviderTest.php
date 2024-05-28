<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Cache\Metadata;

use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle2\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle2\Attribute\Target\ForProperties;
use Sofascore\PurgatoryBundle2\Cache\Metadata\ControllerMetadata;
use Sofascore\PurgatoryBundle2\Cache\Metadata\ControllerMetadataProviderInterface;
use Sofascore\PurgatoryBundle2\Cache\Metadata\PurgeSubscription;
use Sofascore\PurgatoryBundle2\Cache\Metadata\PurgeSubscriptionProvider;
use Sofascore\PurgatoryBundle2\Exception\EntityMetadataNotFoundException;
use Symfony\Component\Routing\Route;

#[CoversClass(PurgeSubscriptionProvider::class)]
final class PurgeSubscriptionProviderTest extends TestCase
{
    #[DataProvider('provideControllerMetadata')]
    public function testNullTarget(ControllerMetadata $controllerMetadata, array $expectedSubscriptions): void
    {
        $controllerMetadataProvider = $this->createMock(ControllerMetadataProviderInterface::class);
        $controllerMetadataProvider->method('provide')
            ->willReturnCallback(function () use ($controllerMetadata) {
                yield $controllerMetadata;
            });

        $propertySubscriptionProvider = new PurgeSubscriptionProvider(
            subscriptionResolvers: [],
            controllerMetadataProvider: $controllerMetadataProvider,
            managerRegistry: $this->createMock(ManagerRegistry::class),
        );

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
    }

    public function testEntityMetadataNotFound(): void
    {
        $controllerMetadataProvider = $this->createMock(ControllerMetadataProviderInterface::class);
        $controllerMetadataProvider->method('provide')
            ->willReturnCallback(function () {
                yield new ControllerMetadata(
                    routeName: 'foo',
                    route: new Route('/foo'),
                    purgeOn: new PurgeOn(
                        class: 'FooEntity',
                        target: new ForProperties(['bar']),
                    ),
                );
            });

        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $managerRegistry->method('getManager')
            ->willReturn(null);

        $propertySubscriptionProvider = new PurgeSubscriptionProvider(
            subscriptionResolvers: [],
            controllerMetadataProvider: $controllerMetadataProvider,
            managerRegistry: $managerRegistry,
        );

        $this->expectException(EntityMetadataNotFoundException::class);
        $this->expectExceptionMessage('Unable to retrieve metadata for entity "FooEntity".');

        [...$propertySubscriptionProvider->provide()];
    }
}
