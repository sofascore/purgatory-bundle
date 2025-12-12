<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Cache\TargetResolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle\Attribute\Target\ForGroups;
use Sofascore\PurgatoryBundle\Cache\RouteMetadata\RouteMetadata;
use Sofascore\PurgatoryBundle\Cache\TargetResolver\ForGroupsResolver;
use Sofascore\PurgatoryBundle\Exception\LogicException;
use Sofascore\PurgatoryBundle\Exception\RuntimeException;
use Symfony\Component\PropertyInfo\PropertyListExtractorInterface;
use Symfony\Component\Routing\Route;

#[CoversClass(ForGroupsResolver::class)]
final class ForGroupsResolverTest extends TestCase
{
    public function testResolve(): void
    {
        $propertyListExtractor = self::createStub(PropertyListExtractorInterface::class);
        $propertyListExtractor->method('getProperties')
            ->with('FooEntity', ['serializer_groups' => ['group1']])
            ->willReturn(['property1', 'property2']);

        $resolver = new ForGroupsResolver($propertyListExtractor);

        $routeMetadata = new RouteMetadata(
            routeName: 'route_foo',
            route: new Route('/foo'),
            purgeOn: new PurgeOn(
                class: 'FooEntity',
                target: $target = new ForGroups(['group1']),
            ),
            reflectionMethod: self::createStub(\ReflectionMethod::class),
        );

        $resolved = $resolver->resolve($target, $routeMetadata);

        self::assertSame(['property1', 'property2'], $resolved);
    }

    public function testExceptionIsThrownWhenPropertiesCannotBeResolved(): void
    {
        $propertyListExtractor = self::createStub(PropertyListExtractorInterface::class);
        $propertyListExtractor->method('getProperties')->willReturn(null);

        $resolver = new ForGroupsResolver($propertyListExtractor);

        $routeMetadata = new RouteMetadata(
            routeName: 'route_foo',
            route: new Route('/foo'),
            purgeOn: new PurgeOn(
                class: 'FooEntity',
                target: $target = new ForGroups(['group1']),
            ),
            reflectionMethod: self::createStub(\ReflectionMethod::class),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Could not resolve properties for groups "group1" in class "FooEntity".');

        $resolver->resolve($target, $routeMetadata);
    }

    public function testExceptionIsThrownWhenSerializerExtractorIsNotAvailable(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('You cannot use the "ForGroups" attribute because the Symfony Serializer component is not installed. Try running "composer require symfony/serializer".');

        new ForGroupsResolver(null);
    }
}
