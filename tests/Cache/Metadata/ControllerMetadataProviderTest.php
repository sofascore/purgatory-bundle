<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Cache\Metadata;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle2\Cache\Metadata\ControllerMetadata;
use Sofascore\PurgatoryBundle2\Cache\Metadata\ControllerMetadataProvider;
use Sofascore\PurgatoryBundle2\Tests\Cache\Metadata\Fixtures\BarController;
use Sofascore\PurgatoryBundle2\Tests\Cache\Metadata\Fixtures\BazController;
use Sofascore\PurgatoryBundle2\Tests\Cache\Metadata\Fixtures\FooController;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

#[CoversClass(ControllerMetadataProvider::class)]
final class ControllerMetadataProviderTest extends TestCase
{
    public function testControllerMetadata(): void
    {
        $collection = new RouteCollection();

        $fooBarRoute = new Route(
            path: '/foo/bar',
            defaults: [
                '_controller' => sprintf('%s::%s', FooController::class, 'barAction'),
            ],
        );
        $fooBazRoute = new Route(
            path: '/foo/baz',
            defaults: [
                '_controller' => 'foo.controller::bazAction',
            ],
        );

        $collection->add(name: 'foo_bar', route: $fooBarRoute);
        $collection->add(name: 'foo_baz', route: $fooBazRoute);

        $router = $this->createMock(RouterInterface::class);
        $router->method('getRouteCollection')
            ->willReturn($collection);

        $provider = new ControllerMetadataProvider(
            router: $router,
            classMap: [
                FooController::class => FooController::class,
                'foo.controller' => FooController::class,
            ],
            routeIgnorePatterns: [],
        );

        /** @var ControllerMetadata[] $metadata */
        $metadata = [...$provider->provide()];

        self::assertContainsOnlyInstancesOf(ControllerMetadata::class, $metadata);
        self::assertCount(3, $metadata);

        self::assertSame('foo_bar', $metadata[0]->routeName);
        self::assertSame('foo_baz', $metadata[1]->routeName);
        self::assertSame('foo_baz', $metadata[2]->routeName);

        self::assertSame($fooBarRoute, $metadata[0]->route);
        self::assertSame($fooBazRoute, $metadata[1]->route);
        self::assertSame($fooBazRoute, $metadata[2]->route);

        self::assertSame('bar', $metadata[0]->purgeOn->class);
        self::assertSame('baz1', $metadata[1]->purgeOn->class);
        self::assertSame('baz2', $metadata[2]->purgeOn->class);

        self::assertSame(FooController::class, $metadata[0]->reflectionMethod->class);
        self::assertSame('barAction', $metadata[0]->reflectionMethod->name);
        self::assertSame(FooController::class, $metadata[1]->reflectionMethod->class);
        self::assertSame('bazAction', $metadata[1]->reflectionMethod->name);
        self::assertSame(FooController::class, $metadata[2]->reflectionMethod->class);
        self::assertSame('bazAction', $metadata[2]->reflectionMethod->name);
    }

    public function testControllerMetadataWitExplicitRoute(): void
    {
        $collection = new RouteCollection();

        $fooBarRoute1 = new Route(
            path: '/foo/bar1',
            defaults: [
                '_controller' => sprintf('%s::%s', BarController::class, 'fooAction'),
            ],
        );
        $fooBarRoute2 = new Route(
            path: '/foo/bar2',
            defaults: [
                '_controller' => sprintf('%s::%s', BarController::class, 'fooAction'),
            ],
        );
        $fooBarRoute3 = new Route(
            path: '/foo/bar3',
            defaults: [
                '_controller' => sprintf('%s::%s', BarController::class, 'fooAction'),
            ],
        );
        $fooBazRoute1 = new Route(
            path: '/foo/baz1',
            defaults: [
                '_controller' => 'bar.controller::bazAction',
            ],
        );
        $fooBazRoute2 = new Route(
            path: '/foo/baz2',
            defaults: [
                '_controller' => 'bar.controller::bazAction',
            ],
        );
        $fooBazRoute3 = new Route(
            path: '/foo/baz3',
            defaults: [
                '_controller' => 'bar.controller::bazAction',
            ],
        );

        $collection->add(name: 'foo_bar1', route: $fooBarRoute1);
        $collection->add(name: 'foo_bar2', route: $fooBarRoute2);
        $collection->add(name: 'foo_bar3', route: $fooBarRoute3);
        $collection->add(name: 'foo_baz1', route: $fooBazRoute1);
        $collection->add(name: 'foo_baz2', route: $fooBazRoute2);
        $collection->add(name: 'foo_baz3', route: $fooBazRoute3);

        $router = $this->createMock(RouterInterface::class);
        $router->method('getRouteCollection')
            ->willReturn($collection);

        $provider = new ControllerMetadataProvider(
            router: $router,
            classMap: [
                BarController::class => BarController::class,
                'bar.controller' => BarController::class,
            ],
            routeIgnorePatterns: [],
        );

        /** @var ControllerMetadata[] $metadata */
        $metadata = [...$provider->provide()];

        self::assertContainsOnlyInstancesOf(ControllerMetadata::class, $metadata);
        self::assertCount(3, $metadata);

        self::assertSame('foo_bar1', $metadata[0]->routeName);
        self::assertSame('foo_baz1', $metadata[1]->routeName);
        self::assertSame('foo_baz3', $metadata[2]->routeName);

        self::assertSame($fooBarRoute1, $metadata[0]->route);
        self::assertSame($fooBazRoute1, $metadata[1]->route);
        self::assertSame($fooBazRoute3, $metadata[2]->route);

        self::assertSame('foo', $metadata[0]->purgeOn->class);
        self::assertSame('foo', $metadata[1]->purgeOn->class);
        self::assertSame('foo', $metadata[2]->purgeOn->class);

        self::assertSame(BarController::class, $metadata[0]->reflectionMethod->class);
        self::assertSame('fooAction', $metadata[0]->reflectionMethod->name);
        self::assertSame(BarController::class, $metadata[1]->reflectionMethod->class);
        self::assertSame('bazAction', $metadata[1]->reflectionMethod->name);
        self::assertSame(BarController::class, $metadata[2]->reflectionMethod->class);
        self::assertSame('bazAction', $metadata[2]->reflectionMethod->name);
    }

    public function testControllerMetadataOnClass(): void
    {
        $collection = new RouteCollection();

        $fooRoute = new Route(
            path: '/foo',
            defaults: [
                '_controller' => BazController::class,
            ],
        );
        $fooBarRoute = new Route(
            path: '/foo/bar',
            defaults: [
                '_controller' => sprintf('%s::%s', BazController::class, 'barAction'),
            ],
        );

        $collection->add(name: 'foo', route: $fooRoute);
        $collection->add(name: 'foo_bar', route: $fooBarRoute);

        $router = $this->createMock(RouterInterface::class);
        $router->method('getRouteCollection')
            ->willReturn($collection);

        $provider = new ControllerMetadataProvider(
            router: $router,
            classMap: [
                BazController::class => BazController::class,
            ],
            routeIgnorePatterns: [],
        );

        /** @var ControllerMetadata[] $metadata */
        $metadata = [...$provider->provide()];

        self::assertContainsOnlyInstancesOf(ControllerMetadata::class, $metadata);
        self::assertCount(3, $metadata);

        self::assertSame('foo', $metadata[0]->routeName);
        self::assertSame('foo_bar', $metadata[1]->routeName);
        self::assertSame('foo_bar', $metadata[2]->routeName);

        self::assertSame($fooRoute, $metadata[0]->route);
        self::assertSame($fooBarRoute, $metadata[1]->route);
        self::assertSame($fooBarRoute, $metadata[2]->route);

        self::assertSame('foo', $metadata[0]->purgeOn->class);
        self::assertSame('foo', $metadata[1]->purgeOn->class);
        self::assertSame('bar', $metadata[2]->purgeOn->class);

        self::assertSame(BazController::class, $metadata[0]->reflectionMethod->class);
        self::assertSame('__invoke', $metadata[0]->reflectionMethod->name);
        self::assertSame(BazController::class, $metadata[1]->reflectionMethod->class);
        self::assertSame('barAction', $metadata[1]->reflectionMethod->name);
        self::assertSame(BazController::class, $metadata[2]->reflectionMethod->class);
        self::assertSame('barAction', $metadata[2]->reflectionMethod->name);
    }

    public function testNotResolvableController(): void
    {
        $router = $this->createMock(RouterInterface::class);

        $collection = new RouteCollection();

        $collection->add(
            name: 'foo_bar',
            route: new Route(
                path: '/foo/bar',
                defaults: [
                    '_controller' => 'nonexistent.controller',
                ],
            ),
        );

        $router->method('getRouteCollection')
            ->willReturn($collection);

        $provider = new ControllerMetadataProvider(
            router: $router,
            classMap: [],
            routeIgnorePatterns: [],
        );

        self::assertCount(0, [...$provider->provide()]);
    }

    public function testRouteIgnorePattern(): void
    {
        $collection = new RouteCollection();

        $fooBarRoute = new Route(
            path: '/foo/bar',
            defaults: [
                '_controller' => sprintf('%s::%s', FooController::class, 'barAction'),
            ],
        );
        $fooBazRoute = new Route(
            path: '/foo/baz',
            defaults: [
                '_controller' => 'foo.controller::bazAction',
            ],
        );

        $collection->add(name: 'foo_bar', route: $fooBarRoute);
        $collection->add(name: 'foo_baz', route: $fooBazRoute);

        $router = $this->createMock(RouterInterface::class);
        $router->method('getRouteCollection')
            ->willReturn($collection);

        $provider = new ControllerMetadataProvider(
            router: $router,
            classMap: [
                FooController::class => FooController::class,
                'foo.controller' => FooController::class,
            ],
            routeIgnorePatterns: ['/^foo_/'],
        );

        /** @var ControllerMetadata[] $metadata */
        $metadata = [...$provider->provide()];

        self::assertCount(0, $metadata);
    }

    public function testRouteWithNoController(): void
    {
        $collection = new RouteCollection();

        $fooBarRoute = new Route(
            path: '/foo/bar',
        );

        $collection->add(name: 'foo_bar', route: $fooBarRoute);

        $router = $this->createMock(RouterInterface::class);
        $router->method('getRouteCollection')
            ->willReturn($collection);

        $provider = new ControllerMetadataProvider(
            router: $router,
            classMap: [
                FooController::class => FooController::class,
                'foo.controller' => FooController::class,
            ],
            routeIgnorePatterns: [],
        );

        /** @var ControllerMetadata[] $metadata */
        $metadata = [...$provider->provide()];

        self::assertCount(0, $metadata);
    }
}
