<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Cache\TargetResolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle\Attribute\Target\ForResponseGroups;
use Sofascore\PurgatoryBundle\Cache\RouteMetadata\RouteMetadata;
use Sofascore\PurgatoryBundle\Cache\TargetResolver\ForGroupsResolver;
use Sofascore\PurgatoryBundle\Cache\TargetResolver\ForResponseGroupsResolver;
use Sofascore\PurgatoryBundle\Exception\LogicException;
use Sofascore\PurgatoryBundle\Tests\Cache\TargetResolver\Fixtures\SerializeController;
use Symfony\Component\HttpKernel\Attribute\Serialize;
use Symfony\Component\PropertyInfo\PropertyListExtractorInterface;
use Symfony\Component\Routing\Route;

#[CoversClass(ForResponseGroupsResolver::class)]
final class ForResponseGroupsResolverTest extends TestCase
{
    #[RequiresMethod(Serialize::class, '__construct')]
    #[TestWith(['singleGroup', ['group1']])]
    #[TestWith(['multipleGroups', ['group1', 'group2']])]
    public function testResolve(string $method, array $expectedGroups): void
    {
        $propertyListExtractor = $this->createMock(PropertyListExtractorInterface::class);
        $propertyListExtractor->expects(self::once())
            ->method('getProperties')
            ->with('FooEntity', ['serializer_groups' => $expectedGroups])
            ->willReturn(['property1', 'property2']);

        $resolver = new ForResponseGroupsResolver(new ForGroupsResolver($propertyListExtractor));

        $routeMetadata = new RouteMetadata(
            routeName: 'route_foo',
            route: new Route('/foo'),
            purgeOn: new PurgeOn(
                class: 'FooEntity',
                target: $target = new ForResponseGroups(),
            ),
            reflectionMethod: new \ReflectionMethod(SerializeController::class, $method),
        );

        $resolved = $resolver->resolve($target, $routeMetadata);

        self::assertSame(['property1', 'property2'], $resolved);
    }

    #[RequiresMethod(Serialize::class, '__construct')]
    public function testExceptionIsThrownWhenRouteIsNotBackedByControllerMethod(): void
    {
        $propertyListExtractor = $this->createMock(PropertyListExtractorInterface::class);
        $propertyListExtractor->expects(self::never())->method('getProperties');

        $resolver = new ForResponseGroupsResolver(new ForGroupsResolver($propertyListExtractor));

        $routeMetadata = new RouteMetadata(
            routeName: 'route_foo',
            route: new Route('/foo'),
            purgeOn: new PurgeOn(
                class: 'FooEntity',
                target: $target = new ForResponseGroups(),
            ),
            reflectionMethod: null,
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The "ForResponseGroups" attribute cannot be used for route "route_foo" because it is not backed by a controller method.');

        $resolver->resolve($target, $routeMetadata);
    }

    #[RequiresMethod(Serialize::class, '__construct')]
    public function testExceptionIsThrownWhenSerializeAttributeIsMissing(): void
    {
        $propertyListExtractor = $this->createMock(PropertyListExtractorInterface::class);
        $propertyListExtractor->expects(self::never())->method('getProperties');

        $resolver = new ForResponseGroupsResolver(new ForGroupsResolver($propertyListExtractor));

        $routeMetadata = new RouteMetadata(
            routeName: 'route_foo',
            route: new Route('/foo'),
            purgeOn: new PurgeOn(
                class: 'FooEntity',
                target: $target = new ForResponseGroups(),
            ),
            reflectionMethod: new \ReflectionMethod(SerializeController::class, 'withoutSerialize'),
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(\sprintf('The "ForResponseGroups" attribute requires the "#[Serialize]" attribute on the "%s::withoutSerialize()" controller method of route "route_foo".', SerializeController::class));

        $resolver->resolve($target, $routeMetadata);
    }

    #[RequiresMethod(Serialize::class, '__construct')]
    #[TestWith(['withoutGroups'])]
    #[TestWith(['emptyGroups'])]
    #[TestWith(['emptyStringGroup'])]
    #[TestWith(['nonStringGroup'])]
    #[TestWith(['nonStringGroups'])]
    #[TestWith(['nonListGroups'])]
    public function testExceptionIsThrownWhenSerializationGroupsAreNotValid(string $method): void
    {
        $propertyListExtractor = $this->createMock(PropertyListExtractorInterface::class);
        $propertyListExtractor->expects(self::never())->method('getProperties');

        $resolver = new ForResponseGroupsResolver(new ForGroupsResolver($propertyListExtractor));

        $routeMetadata = new RouteMetadata(
            routeName: 'route_foo',
            route: new Route('/foo'),
            purgeOn: new PurgeOn(
                class: 'FooEntity',
                target: $target = new ForResponseGroups(),
            ),
            reflectionMethod: new \ReflectionMethod(SerializeController::class, $method),
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(\sprintf('The "ForResponseGroups" attribute requires the "#[Serialize]" attribute on the "%s::%s()" controller method of route "route_foo" to define at least one serialization group.', SerializeController::class, $method));

        $resolver->resolve($target, $routeMetadata);
    }
}
