<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Cache\RouteMetadata;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\CompoundValues;
use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\DynamicValues;
use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\EnumValues;
use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\PropertyValues;
use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\RawValues;
use Sofascore\PurgatoryBundle2\Attribute\Target\ForGroups;
use Sofascore\PurgatoryBundle2\Attribute\Target\ForProperties;
use Sofascore\PurgatoryBundle2\Cache\RouteMetadata\RouteMetadata;
use Sofascore\PurgatoryBundle2\Cache\RouteMetadata\YamlMetadataProvider;
use Sofascore\PurgatoryBundle2\Exception\InvalidArgumentException;
use Sofascore\PurgatoryBundle2\Exception\RouteNotFoundException;
use Sofascore\PurgatoryBundle2\Exception\RuntimeException;
use Sofascore\PurgatoryBundle2\Exception\UnknownYamlTagException;
use Sofascore\PurgatoryBundle2\Listener\Enum\Action;
use Sofascore\PurgatoryBundle2\Tests\Cache\RouteMetadata\Fixtures\DummyEnum;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

#[CoversClass(YamlMetadataProvider::class)]
final class YamlMetadataProviderTest extends TestCase
{
    public function testRouteMetadata(): void
    {
        $collection = new RouteCollection();

        $fooBarRoute = new Route(path: '/foo/bar');
        $fooBazRoute = new Route(path: '/foo/baz');

        $collection->add(name: 'foo_bar', route: $fooBarRoute);
        $collection->add(name: 'foo_baz', route: $fooBazRoute);

        $router = $this->createMock(RouterInterface::class);
        $router->method('getRouteCollection')
            ->willReturn($collection);

        $provider = new YamlMetadataProvider(
            router: $router,
            files: [
                __DIR__.'/Fixtures/config/purge_on_simple.yaml',
            ],
        );

        /** @var RouteMetadata[] $metadata */
        $metadata = [...$provider->provide()];

        self::assertContainsOnlyInstancesOf(RouteMetadata::class, $metadata);
        self::assertCount(2, $metadata);

        self::assertSame('foo_bar', $metadata[0]->routeName);
        self::assertSame('foo_baz', $metadata[1]->routeName);

        self::assertSame($fooBarRoute, $metadata[0]->route);
        self::assertSame($fooBazRoute, $metadata[1]->route);

        self::assertSame('Foo', $metadata[0]->purgeOn->class);
        self::assertSame('Bar', $metadata[1]->purgeOn->class);

        self::assertNull($metadata[0]->purgeOn->target);
        self::assertEquals(new ForProperties('bar'), $metadata[1]->purgeOn->target);

        self::assertNull($metadata[0]->purgeOn->routeParams);
        self::assertEquals([
            'param1' => new PropertyValues('bar'),
            'param2' => new PropertyValues('bar', 'baz'),
        ], $metadata[1]->purgeOn->routeParams);

        self::assertNull($metadata[0]->purgeOn->if);
        self::assertEquals(new Expression('obj.isActive() === true'), $metadata[1]->purgeOn->if);

        self::assertNull($metadata[0]->purgeOn->actions);
        self::assertSame([Action::Create], $metadata[1]->purgeOn->actions);
    }

    public function testRouteMetadataWithTags(): void
    {
        $collection = new RouteCollection();

        $fooBarRoute = new Route(path: '/foo/bar');

        $collection->add(name: 'foo_bar', route: $fooBarRoute);

        $router = $this->createMock(RouterInterface::class);
        $router->method('getRouteCollection')
            ->willReturn($collection);

        $provider = new YamlMetadataProvider(
            router: $router,
            files: [
                __DIR__.'/Fixtures/config/purge_on_with_tags.yaml',
            ],
        );

        /** @var RouteMetadata[] $metadata */
        $metadata = [...$provider->provide()];

        self::assertContainsOnlyInstancesOf(RouteMetadata::class, $metadata);
        self::assertCount(1, $metadata);
        self::assertSame('foo_bar', $metadata[0]->routeName);
        self::assertSame($fooBarRoute, $metadata[0]->route);
        self::assertSame('Foo', $metadata[0]->purgeOn->class);
        self::assertEquals(new ForGroups(['group1', 'group2']), $metadata[0]->purgeOn->target);
        self::assertEquals([
            'param1' => new PropertyValues('bar', 'baz'),
            'param2' => new RawValues(1, 2),
            'param3' => new EnumValues(DummyEnum::class),
            'param4' => new CompoundValues(
                new RawValues(0),
                new EnumValues(DummyEnum::class),
            ),
            'param5' => new DynamicValues('foo'),
            'param6' => new DynamicValues('foo', 'bar'),
        ], $metadata[0]->purgeOn->routeParams);
        self::assertNull($metadata[0]->purgeOn->if);
        self::assertNull($metadata[0]->purgeOn->actions);
    }

    public function testMultipleRouteMetadata(): void
    {
        $collection = new RouteCollection();

        $fooBarRoute = new Route(path: '/foo/bar');

        $collection->add(name: 'foo_bar', route: $fooBarRoute);

        $router = $this->createMock(RouterInterface::class);
        $router->method('getRouteCollection')
            ->willReturn($collection);

        $provider = new YamlMetadataProvider(
            router: $router,
            files: [
                __DIR__.'/Fixtures/config/purge_on_multiple.yaml',
            ],
        );

        /** @var RouteMetadata[] $metadata */
        $metadata = [...$provider->provide()];

        self::assertContainsOnlyInstancesOf(RouteMetadata::class, $metadata);
        self::assertCount(2, $metadata);

        self::assertSame('foo_bar', $metadata[0]->routeName);
        self::assertSame('foo_bar', $metadata[1]->routeName);

        self::assertSame($fooBarRoute, $metadata[0]->route);
        self::assertSame($fooBarRoute, $metadata[1]->route);

        self::assertSame('Foo', $metadata[0]->purgeOn->class);
        self::assertSame('Qux', $metadata[1]->purgeOn->class);

        self::assertEquals(new ForProperties(['bar', 'baz']), $metadata[0]->purgeOn->target);
        self::assertEquals(new ForProperties('corge'), $metadata[1]->purgeOn->target);

        self::assertEquals([
            'param1' => new RawValues(1, 2),
            'param2' => new EnumValues(DummyEnum::class),
        ], $metadata[0]->purgeOn->routeParams);
        self::assertNull($metadata[1]->purgeOn->routeParams);

        self::assertNull($metadata[0]->purgeOn->if);
        self::assertEquals(new Expression('obj.isActive() === true'), $metadata[1]->purgeOn->if);

        self::assertSame([Action::Create, Action::Update], $metadata[0]->purgeOn->actions);
        self::assertSame([Action::Delete], $metadata[1]->purgeOn->actions);
    }

    public function testExceptionIsThrownForInvalidYaml(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('getRouteCollection')
            ->willReturn(new RouteCollection());

        $provider = new YamlMetadataProvider(
            router: $router,
            files: [
                $file = __DIR__.'/Fixtures/config/purge_on_invalid.yaml',
            ],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('The file "%s" does not contain valid YAML: ', $file));

        iterator_to_array($provider->provide());
    }

    public function testExceptionIsThrownIfParsedYamlIsNotAnArray(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('getRouteCollection')
            ->willReturn(new RouteCollection());

        $provider = new YamlMetadataProvider(
            router: $router,
            files: [
                $file = __DIR__.'/Fixtures/config/purge_on_not_array.yaml',
            ],
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(sprintf('Expected the parsed YAML of file "%s" to be an array, got "string".', $file));

        iterator_to_array($provider->provide());
    }

    public function testExceptionIsThrownForUnsupportedKeys(): void
    {
        $collection = new RouteCollection();
        $collection->add(name: 'foo_bar', route: new Route(path: '/foo/bar'));

        $router = $this->createMock(RouterInterface::class);
        $router->method('getRouteCollection')
            ->willReturn($collection);

        $provider = new YamlMetadataProvider(
            router: $router,
            files: [
                __DIR__.'/Fixtures/config/purge_on_unsupported_keys.yaml',
            ],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Route "foo_bar" contains unsupported keys "one", "two", supported ones are');

        iterator_to_array($provider->provide());
    }

    public function testExceptionIsThrownForInvalidRoute(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('getRouteCollection')
            ->willReturn(new RouteCollection());

        $provider = new YamlMetadataProvider(
            router: $router,
            files: [
                __DIR__.'/Fixtures/config/purge_on_simple.yaml',
            ],
        );

        $this->expectException(RouteNotFoundException::class);
        $this->expectExceptionMessage('The route "foo_bar" does not exist.');

        iterator_to_array($provider->provide());
    }

    #[TestWith(['purge_on_with_unknown_target_tag.yaml',  'Unknown YAML tag "for_unknown" provided, known tags are "for_groups", "for_properties".'])]
    #[TestWith(['purge_on_with_unknown_route_param_tag.yaml',  'Unknown YAML tag "unknown" provided, known tags are "compound", "dynamic", "enum", "property", "raw".'])]
    public function testExceptionIsThrownForUnknownTags(string $file, string $message): void
    {
        $collection = new RouteCollection();
        $collection->add(name: 'foo_bar', route: new Route(path: '/foo/bar'));

        $router = $this->createMock(RouterInterface::class);
        $router->method('getRouteCollection')
            ->willReturn($collection);

        $provider = new YamlMetadataProvider(
            router: $router,
            files: [
                __DIR__.'/Fixtures/config/'.$file,
            ],
        );

        $this->expectException(UnknownYamlTagException::class);
        $this->expectExceptionMessage($message);

        iterator_to_array($provider->provide());
    }
}
