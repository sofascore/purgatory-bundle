<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Cache\TargetResolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle\Attribute\Target\ForProperties;
use Sofascore\PurgatoryBundle\Cache\RouteMetadata\RouteMetadata;
use Sofascore\PurgatoryBundle\Cache\TargetResolver\ForPropertiesResolver;
use Symfony\Component\Routing\Route;

#[CoversClass(ForPropertiesResolver::class)]
final class ForPropertiesResolverTest extends TestCase
{
    public function testResolve(): void
    {
        $resolver = new ForPropertiesResolver();

        $routeMetadata = new RouteMetadata(
            routeName: 'route_foo',
            route: new Route('/foo'),
            purgeOn: new PurgeOn(
                class: 'FooEntity',
                target: $target = new ForProperties(['property1', 'property2']),
            ),
            reflectionMethod: $this->createMock(\ReflectionMethod::class),
        );

        $resolved = $resolver->resolve($target, $routeMetadata);

        self::assertSame(['property1', 'property2'], $resolved);
    }
}
