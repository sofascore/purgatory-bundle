<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Cache\TargetResolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle2\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle2\Attribute\Target\ForProperties;
use Sofascore\PurgatoryBundle2\Cache\Metadata\ControllerMetadata;
use Sofascore\PurgatoryBundle2\Cache\TargetResolver\ForPropertiesResolver;
use Symfony\Component\Routing\Route;

#[CoversClass(ForPropertiesResolver::class)]
final class ForPropertiesResolverTest extends TestCase
{
    public function testResolve(): void
    {
        $resolver = new ForPropertiesResolver();

        $controllerMetadata = new ControllerMetadata(
            routeName: 'route_foo',
            route: new Route('/foo'),
            purgeOn: new PurgeOn(
                class: 'FooEntity',
                target: $target = new ForProperties(['property1', 'property2']),
            ),
            reflectionMethod: $this->createMock(\ReflectionMethod::class),
        );

        $resolved = $resolver->resolve($target, $controllerMetadata);

        self::assertSame(['property1', 'property2'], $resolved);
    }
}
