<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Cache\Metadata;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle2\Cache\Metadata\ControllerMetadata;
use Sofascore\PurgatoryBundle2\Cache\Metadata\ControllerMetadataProvider;
use Sofascore\PurgatoryBundle2\Exception\ClassNotResolvableException;
use Sofascore\PurgatoryBundle2\Exception\RouteNotFoundException;
use Sofascore\PurgatoryBundle2\Tests\Cache\Metadata\Fixtures\BarController;
use Sofascore\PurgatoryBundle2\Tests\Cache\Metadata\Fixtures\FooController;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

#[CoversClass(ControllerMetadataProvider::class)]
class ControllerMetadataProviderTest extends TestCase
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
        );

        $this->expectException(ClassNotResolvableException::class);
        $this->expectExceptionMessage('Could not resolve "nonexistent.controller"');

        [...$provider->provide()];
    }

    public function testNonexistentRoute(): void
    {
        $router = $this->createMock(RouterInterface::class);

        $collection = new RouteCollection();

        $collection->add(
            name: 'foo_bar',
            route: new Route(
                path: '/foo/bar',
                defaults: [
                    '_controller' => sprintf('%s::%s', BarController::class, 'fooAction'),
                ],
            ),
        );

        $router->method('getRouteCollection')
            ->willReturn($collection);

        $provider = new ControllerMetadataProvider(
            router: $router,
            classMap: [
                BarController::class => BarController::class,
            ],
        );

        $this->expectException(RouteNotFoundException::class);
        $this->expectExceptionMessage("Route 'nonexistent_route' not found.");

        [...$provider->provide()];
    }
}
