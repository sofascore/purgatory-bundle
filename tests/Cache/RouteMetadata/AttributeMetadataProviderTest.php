<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Cache\RouteMetadata;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Attribute\PurgeContext;
use Sofascore\PurgatoryBundle\Cache\RouteMetadata\AttributeMetadataProvider;
use Sofascore\PurgatoryBundle\Cache\RouteMetadata\RouteMetadata;
use Sofascore\PurgatoryBundle\Exception\LogicException;
use Sofascore\PurgatoryBundle\Tests\Cache\RouteMetadata\Fixtures\BarController;
use Sofascore\PurgatoryBundle\Tests\Cache\RouteMetadata\Fixtures\BazController;
use Sofascore\PurgatoryBundle\Tests\Cache\RouteMetadata\Fixtures\ContextController;
use Sofascore\PurgatoryBundle\Tests\Cache\RouteMetadata\Fixtures\ExtendedContextController;
use Sofascore\PurgatoryBundle\Tests\Cache\RouteMetadata\Fixtures\FooController;
use Sofascore\PurgatoryBundle\Tests\Cache\RouteMetadata\Fixtures\MethodContextController;
use Sofascore\PurgatoryBundle\Tests\Cache\RouteMetadata\Fixtures\PurgePriority;
use Sofascore\PurgatoryBundle\Tests\Cache\RouteMetadata\Fixtures\TwoClassContextsController;
use Sofascore\PurgatoryBundle\Tests\Cache\RouteMetadata\Fixtures\TwoMethodContextsController;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

#[CoversClass(AttributeMetadataProvider::class)]
final class AttributeMetadataProviderTest extends TestCase
{
    public function testRouteMetadata(): void
    {
        $collection = new RouteCollection();

        $fooBarRoute = new Route(
            path: '/foo/bar',
            defaults: [
                '_controller' => \sprintf('%s::%s', FooController::class, 'barAction'),
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

        $router = self::createStub(RouterInterface::class);
        $router->method('getRouteCollection')
            ->willReturn($collection);

        $provider = new AttributeMetadataProvider(
            router: $router,
            classMap: [
                FooController::class => FooController::class,
                'foo.controller' => FooController::class,
            ],
            routeIgnorePatterns: [],
        );

        /** @var RouteMetadata[] $metadata */
        $metadata = [...$provider->provide()];

        self::assertContainsOnlyInstancesOf(RouteMetadata::class, $metadata);
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

    public function testRouteMetadataWitExplicitRoute(): void
    {
        $collection = new RouteCollection();

        $fooBarRoute1 = new Route(
            path: '/foo/bar1',
            defaults: [
                '_controller' => \sprintf('%s::%s', BarController::class, 'fooAction'),
            ],
        );
        $fooBarRoute2 = new Route(
            path: '/foo/bar2',
            defaults: [
                '_controller' => \sprintf('%s::%s', BarController::class, 'fooAction'),
            ],
        );
        $fooBarRoute3 = new Route(
            path: '/foo/bar3',
            defaults: [
                '_controller' => \sprintf('%s::%s', BarController::class, 'fooAction'),
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

        $router = self::createStub(RouterInterface::class);
        $router->method('getRouteCollection')
            ->willReturn($collection);

        $provider = new AttributeMetadataProvider(
            router: $router,
            classMap: [
                BarController::class => BarController::class,
                'bar.controller' => BarController::class,
            ],
            routeIgnorePatterns: [],
        );

        /** @var RouteMetadata[] $metadata */
        $metadata = [...$provider->provide()];

        self::assertContainsOnlyInstancesOf(RouteMetadata::class, $metadata);
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

    public function testRouteMetadataOnClass(): void
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
                '_controller' => \sprintf('%s::%s', BazController::class, 'barAction'),
            ],
        );

        $collection->add(name: 'foo', route: $fooRoute);
        $collection->add(name: 'foo_bar', route: $fooBarRoute);

        $router = self::createStub(RouterInterface::class);
        $router->method('getRouteCollection')
            ->willReturn($collection);

        $provider = new AttributeMetadataProvider(
            router: $router,
            classMap: [
                BazController::class => BazController::class,
            ],
            routeIgnorePatterns: [],
        );

        /** @var RouteMetadata[] $metadata */
        $metadata = [...$provider->provide()];

        self::assertContainsOnlyInstancesOf(RouteMetadata::class, $metadata);
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

    public function testPurgeContextOnClassAndMethod(): void
    {
        $collection = new RouteCollection();

        $collection->add(name: 'context_foo', route: new Route(
            path: '/context/foo',
            defaults: [
                '_controller' => \sprintf('%s::%s', ContextController::class, 'fooAction'),
            ],
        ));
        $collection->add(name: 'context_bar', route: new Route(
            path: '/context/bar',
            defaults: [
                '_controller' => \sprintf('%s::%s', ContextController::class, 'barAction'),
            ],
        ));

        $router = self::createStub(RouterInterface::class);
        $router->method('getRouteCollection')
            ->willReturn($collection);

        $provider = new AttributeMetadataProvider(
            router: $router,
            classMap: [
                ContextController::class => ContextController::class,
            ],
            routeIgnorePatterns: [],
        );

        /** @var RouteMetadata[] $metadata */
        $metadata = [...$provider->provide()];

        self::assertCount(4, $metadata);

        self::assertSame('context_foo', $metadata[0]->routeName);
        self::assertSame('foo', $metadata[0]->purgeOn->class);
        self::assertSame(['varnish' => ['tags' => ['social'], 'ttl' => 60], 'throttle' => 0], $metadata[0]->context);

        self::assertSame('context_bar', $metadata[1]->routeName);
        self::assertSame('class_level', $metadata[1]->purgeOn->class);
        self::assertSame(['varnish' => ['ttl' => 5], 'throttle' => 30], $metadata[1]->context);
        self::assertSame(['throttle' => 30], $metadata[1]->purgeOn->context);

        self::assertSame('context_bar', $metadata[2]->routeName);
        self::assertSame('bar', $metadata[2]->purgeOn->class);
        self::assertSame(['varnish' => ['ttl' => 5], 'throttle' => null], $metadata[2]->context);

        self::assertSame('context_bar', $metadata[3]->routeName);
        self::assertSame('baz', $metadata[3]->purgeOn->class);
        self::assertSame(['varnish' => ['tags' => ['comment']], 'throttle' => 8], $metadata[3]->context);
    }

    public function testPurgeContextOnMethod(): void
    {
        $collection = new RouteCollection();

        $collection->add(name: 'method_context_foo', route: new Route(
            path: '/method-context/foo',
            defaults: [
                '_controller' => \sprintf('%s::%s', MethodContextController::class, 'fooAction'),
            ],
        ));
        $collection->add(name: 'method_context_baz', route: new Route(
            path: '/method-context/baz',
            defaults: [
                '_controller' => \sprintf('%s::%s', MethodContextController::class, 'bazAction'),
            ],
        ));

        $router = self::createStub(RouterInterface::class);
        $router->method('getRouteCollection')
            ->willReturn($collection);

        $provider = new AttributeMetadataProvider(
            router: $router,
            classMap: [
                MethodContextController::class => MethodContextController::class,
            ],
            routeIgnorePatterns: [],
        );

        /** @var RouteMetadata[] $metadata */
        $metadata = [...$provider->provide()];

        self::assertCount(3, $metadata);

        self::assertSame('method_context_foo', $metadata[0]->routeName);
        self::assertSame('foo', $metadata[0]->purgeOn->class);
        self::assertSame(['tags' => ['post']], $metadata[0]->context);

        self::assertSame('method_context_foo', $metadata[1]->routeName);
        self::assertSame('bar', $metadata[1]->purgeOn->class);
        self::assertSame(['tags' => ['comment'], 'throttle' => 8], $metadata[1]->context);

        self::assertSame('method_context_baz', $metadata[2]->routeName);
        self::assertSame('baz', $metadata[2]->purgeOn->class);
        self::assertSame(['throttle' => 8], $metadata[2]->context);
    }

    public function testPurgeContextSubclass(): void
    {
        $collection = new RouteCollection();

        $collection->add(name: 'extended_context_foo', route: new Route(
            path: '/extended-context/foo',
            defaults: [
                '_controller' => \sprintf('%s::%s', ExtendedContextController::class, 'fooAction'),
            ],
        ));
        $collection->add(name: 'extended_context_baz', route: new Route(
            path: '/extended-context/baz',
            defaults: [
                '_controller' => \sprintf('%s::%s', ExtendedContextController::class, 'bazAction'),
            ],
        ));

        $router = self::createStub(RouterInterface::class);
        $router->method('getRouteCollection')
            ->willReturn($collection);

        $provider = new AttributeMetadataProvider(
            router: $router,
            classMap: [
                ExtendedContextController::class => ExtendedContextController::class,
            ],
            routeIgnorePatterns: [],
        );

        /** @var RouteMetadata[] $metadata */
        $metadata = [...$provider->provide()];

        self::assertCount(3, $metadata);

        self::assertSame('extended_context_foo', $metadata[0]->routeName);
        self::assertSame('foo', $metadata[0]->purgeOn->class);
        self::assertSame(['priority' => 'low', 'throttle' => 8], $metadata[0]->context);

        self::assertSame('extended_context_foo', $metadata[1]->routeName);
        self::assertSame('bar', $metadata[1]->purgeOn->class);
        self::assertSame(['priority' => 'high', 'throttle' => 8], $metadata[1]->context);

        self::assertSame('extended_context_baz', $metadata[2]->routeName);
        self::assertSame('baz', $metadata[2]->purgeOn->class);
        self::assertSame(['priority' => 'high'], $metadata[2]->context);
    }

    #[TestWith([TwoClassContextsController::class, TwoClassContextsController::class, 'class'])]
    #[TestWith([TwoMethodContextsController::class, TwoMethodContextsController::class.'::fooAction()', 'method'])]
    public function testMoreThanOnePurgeContextIsRejected(string $controller, string $name, string $kind): void
    {
        $collection = new RouteCollection();

        $collection->add(name: 'foo', route: new Route(
            path: '/foo',
            defaults: [
                '_controller' => \sprintf('%s::%s', $controller, 'fooAction'),
            ],
        ));

        $router = self::createStub(RouterInterface::class);
        $router->method('getRouteCollection')
            ->willReturn($collection);

        $provider = new AttributeMetadataProvider(
            router: $router,
            classMap: [
                $controller => $controller,
            ],
            routeIgnorePatterns: [],
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(\sprintf(
            'The "%s" controller %s can only have one "#[PurgeContext]" attribute or subclass of it, found "%s", "%s".',
            $name,
            $kind,
            PurgeContext::class,
            PurgePriority::class,
        ));

        [...$provider->provide()];
    }

    public function testNotResolvableController(): void
    {
        $router = self::createStub(RouterInterface::class);

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

        $provider = new AttributeMetadataProvider(
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
                '_controller' => \sprintf('%s::%s', FooController::class, 'barAction'),
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

        $router = self::createStub(RouterInterface::class);
        $router->method('getRouteCollection')
            ->willReturn($collection);

        $provider = new AttributeMetadataProvider(
            router: $router,
            classMap: [
                FooController::class => FooController::class,
                'foo.controller' => FooController::class,
            ],
            routeIgnorePatterns: ['/^foo_/'],
        );

        /** @var RouteMetadata[] $metadata */
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

        $router = self::createStub(RouterInterface::class);
        $router->method('getRouteCollection')
            ->willReturn($collection);

        $provider = new AttributeMetadataProvider(
            router: $router,
            classMap: [
                FooController::class => FooController::class,
                'foo.controller' => FooController::class,
            ],
            routeIgnorePatterns: [],
        );

        /** @var RouteMetadata[] $metadata */
        $metadata = [...$provider->provide()];

        self::assertCount(0, $metadata);
    }
}
