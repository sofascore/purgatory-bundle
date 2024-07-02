<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Cache\TargetResolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle2\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle2\Attribute\Target\ForGroups;
use Sofascore\PurgatoryBundle2\Cache\RouteMetadata\RouteMetadata;
use Sofascore\PurgatoryBundle2\Cache\TargetResolver\ForGroupsResolver;
use Symfony\Component\PropertyInfo\PropertyListExtractorInterface;
use Symfony\Component\Routing\Route;

#[CoversClass(ForGroupsResolver::class)]
final class ForGroupsResolverTest extends TestCase
{
    public function testResolve(): void
    {
        $propertyListExtractor = $this->createMock(PropertyListExtractorInterface::class);
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
            reflectionMethod: $this->createMock(\ReflectionMethod::class),
        );

        $resolved = $resolver->resolve($target, $routeMetadata);

        self::assertSame(['property1', 'property2'], $resolved);
    }
}
