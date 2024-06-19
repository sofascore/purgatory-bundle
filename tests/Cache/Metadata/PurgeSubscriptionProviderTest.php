<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Cache\Metadata;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Sofascore\PurgatoryBundle2\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\PropertyValues;
use Sofascore\PurgatoryBundle2\Attribute\Target\ForProperties;
use Sofascore\PurgatoryBundle2\Cache\Metadata\ControllerMetadata;
use Sofascore\PurgatoryBundle2\Cache\Metadata\ControllerMetadataProviderInterface;
use Sofascore\PurgatoryBundle2\Cache\Metadata\PurgeSubscription;
use Sofascore\PurgatoryBundle2\Cache\Metadata\PurgeSubscriptionProvider;
use Sofascore\PurgatoryBundle2\Cache\PropertyResolver\SubscriptionResolverInterface;
use Sofascore\PurgatoryBundle2\Cache\TargetResolver\TargetResolverInterface;
use Sofascore\PurgatoryBundle2\Exception\EntityMetadataNotFoundException;
use Sofascore\PurgatoryBundle2\Listener\Enum\Action;
use Sofascore\PurgatoryBundle2\Tests\Cache\Metadata\Fixtures\DummyTarget;
use Sofascore\PurgatoryBundle2\Tests\Cache\Metadata\Fixtures\FooController;
use Symfony\Component\Routing\Route;

#[CoversClass(PurgeSubscriptionProvider::class)]
final class PurgeSubscriptionProviderTest extends TestCase
{
    #[DataProvider('provideControllerMetadataWithoutTarget')]
    public function testWithoutTarget(ControllerMetadata $controllerMetadata, array $expectedSubscriptions): void
    {
        $controllerMetadataProvider = $this->createMock(ControllerMetadataProviderInterface::class);
        $controllerMetadataProvider->method('provide')
            ->willReturnCallback(function () use ($controllerMetadata) {
                yield $controllerMetadata;
            });

        $targetResolverLocator = $this->createMock(ContainerInterface::class);
        $targetResolverLocator->expects(self::never())->method('get');

        $purgeSubscriptionProvider = new PurgeSubscriptionProvider(
            subscriptionResolvers: [],
            controllerMetadataProvider: $controllerMetadataProvider,
            managerRegistry: $this->createMock(ManagerRegistry::class),
            targetResolverLocator: $targetResolverLocator,
        );

        /** @var PurgeSubscription[] $propertySubscriptions */
        $propertySubscriptions = [...$purgeSubscriptionProvider->provide()];

        self::assertCount(\count($expectedSubscriptions), $propertySubscriptions);
        self::assertEquals($expectedSubscriptions, $propertySubscriptions);
    }

    public static function provideControllerMetadataWithoutTarget(): iterable
    {
        $route = new Route('/foo');
        yield 'PurgeOn for route without params' => [
            'controllerMetadata' => new ControllerMetadata(
                routeName: 'foo',
                route: $route,
                purgeOn: new PurgeOn(
                    class: 'FooEntity',
                ),
                reflectionMethod: new \ReflectionMethod(FooController::class, 'barAction'),
            ),
            'expectedSubscriptions' => [
                new PurgeSubscription(
                    class: 'FooEntity',
                    property: null,
                    routeParams: [],
                    routeName: 'foo',
                    route: $route,
                    actions: null,
                    if: null,
                ),
            ],
        ];

        $route = new Route('/foo/{bar}');
        yield 'PurgeOn for route with params' => [
            'controllerMetadata' => new ControllerMetadata(
                routeName: 'foo',
                route: $route,
                purgeOn: new PurgeOn(
                    class: 'FooEntity',
                    routeParams: ['bar' => 'baz'],
                ),
                reflectionMethod: new \ReflectionMethod(FooController::class, 'barAction'),
            ),
            'expectedSubscriptions' => [
                new PurgeSubscription(
                    class: 'FooEntity',
                    property: null,
                    routeParams: ['bar' => new PropertyValues('baz')],
                    routeName: 'foo',
                    route: $route,
                    actions: null,
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
                reflectionMethod: new \ReflectionMethod(FooController::class, 'barAction'),
            ),
            'expectedSubscriptions' => [
                new PurgeSubscription(
                    class: 'FooEntity',
                    property: null,
                    routeParams: ['bar' => new PropertyValues('bar'), 'baz' => new PropertyValues('baz')],
                    routeName: 'foo',
                    route: $route,
                    actions: null,
                    if: null,
                ),
            ],
        ];
    }

    #[DataProvider('provideControllerMetadataWithTarget')]
    public function testWithTarget(ControllerMetadata $controllerMetadata, array $targetResolverReturn, array $expectedSubscriptions): void
    {
        $subscriptionResolver = $this->createMock(SubscriptionResolverInterface::class);
        $subscriptionResolver->method('resolveSubscription')
            ->willReturnCallback(function () use ($expectedSubscriptions) {
                static $i = 0;

                yield $expectedSubscriptions[$i++];

                return true;
            });

        $controllerMetadataProvider = $this->createMock(ControllerMetadataProviderInterface::class);
        $controllerMetadataProvider->method('provide')
            ->willReturnCallback(function () use ($controllerMetadata) {
                yield $controllerMetadata;
            });

        $classMetadata = $this->createMock(ClassMetadata::class);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')
            ->with('FooEntity')
            ->willReturn($classMetadata);

        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $managerRegistry->method('getManagerForClass')
            ->with('FooEntity')
            ->willReturn($entityManager);

        $dummyTargetResolver = $this->createMock(TargetResolverInterface::class);
        $dummyTargetResolver->method('resolve')
            ->with($controllerMetadata->purgeOn->target, $controllerMetadata)
            ->willReturn($targetResolverReturn);

        $targetResolverLocator = $this->createMock(ContainerInterface::class);
        $targetResolverLocator->method('get')
            ->with(DummyTarget::class)
            ->willReturn($dummyTargetResolver);

        $purgeSubscriptionProvider = new PurgeSubscriptionProvider(
            subscriptionResolvers: [$subscriptionResolver],
            controllerMetadataProvider: $controllerMetadataProvider,
            managerRegistry: $managerRegistry,
            targetResolverLocator: $targetResolverLocator,
        );

        /** @var PurgeSubscription[] $propertySubscriptions */
        $propertySubscriptions = [...$purgeSubscriptionProvider->provide()];

        self::assertCount(\count($expectedSubscriptions), $propertySubscriptions);
        self::assertEquals($expectedSubscriptions, $propertySubscriptions);
    }

    public static function provideControllerMetadataWithTarget(): iterable
    {
        $route = new Route('/foo');
        yield 'PurgeOn for route without params' => [
            'controllerMetadata' => new ControllerMetadata(
                routeName: 'foo',
                route: $route,
                purgeOn: new PurgeOn(
                    class: 'FooEntity',
                    target: new DummyTarget(),
                ),
                reflectionMethod: new \ReflectionMethod(FooController::class, 'barAction'),
            ),
            'targetResolverReturn' => ['foo', 'bar'],
            'expectedSubscriptions' => [
                new PurgeSubscription(
                    class: 'FooEntity',
                    property: 'foo',
                    routeParams: [],
                    routeName: 'foo',
                    route: $route,
                    actions: Action::cases(),
                    if: null,
                ),
                new PurgeSubscription(
                    class: 'FooEntity',
                    property: 'bar',
                    routeParams: [],
                    routeName: 'bar',
                    route: $route,
                    actions: Action::cases(),
                    if: null,
                ),
            ],
        ];

        $route = new Route('/foo/{bar}');
        yield 'PurgeOn for route with params' => [
            'controllerMetadata' => new ControllerMetadata(
                routeName: 'foo',
                route: $route,
                purgeOn: new PurgeOn(
                    class: 'FooEntity',
                    target: new DummyTarget(),
                    routeParams: ['bar' => 'baz'],
                ),
                reflectionMethod: new \ReflectionMethod(FooController::class, 'barAction'),
            ),
            'targetResolverReturn' => ['bar', 'baz'],
            'expectedSubscriptions' => [
                new PurgeSubscription(
                    class: 'FooEntity',
                    property: 'bar',
                    routeParams: ['bar' => new PropertyValues('baz')],
                    routeName: 'foo',
                    route: $route,
                    actions: Action::cases(),
                    if: null,
                ),
                new PurgeSubscription(
                    class: 'FooEntity',
                    property: 'baz',
                    routeParams: ['bar' => new PropertyValues('baz')],
                    routeName: 'bar',
                    route: $route,
                    actions: Action::cases(),
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
                    target: new DummyTarget(),
                ),
                reflectionMethod: new \ReflectionMethod(FooController::class, 'barAction'),
            ),
            'targetResolverReturn' => ['qux', 'corge'],
            'expectedSubscriptions' => [
                new PurgeSubscription(
                    class: 'FooEntity',
                    property: 'qux',
                    routeParams: ['bar' => new PropertyValues('bar'), 'baz' => new PropertyValues('baz')],
                    routeName: 'foo',
                    route: $route,
                    actions: Action::cases(),
                    if: null,
                ),
                new PurgeSubscription(
                    class: 'FooEntity',
                    property: 'corge',
                    routeParams: ['bar' => new PropertyValues('bar'), 'baz' => new PropertyValues('baz')],
                    routeName: 'foo',
                    route: $route,
                    actions: Action::cases(),
                    if: null,
                ),
            ],
        ];
    }

    public function testExceptionIsThrownWhenEntityMetadataIsNotFound(): void
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
                    reflectionMethod: new \ReflectionMethod(FooController::class, 'barAction'),
                );
            });

        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $managerRegistry->method('getManager')
            ->willReturn(null);

        $purgeSubscriptionProvider = new PurgeSubscriptionProvider(
            subscriptionResolvers: [],
            controllerMetadataProvider: $controllerMetadataProvider,
            managerRegistry: $managerRegistry,
            targetResolverLocator: $this->createMock(ContainerInterface::class),
        );

        $this->expectException(EntityMetadataNotFoundException::class);
        $this->expectExceptionMessage('Unable to retrieve metadata for entity "FooEntity".');

        [...$purgeSubscriptionProvider->provide()];
    }
}
