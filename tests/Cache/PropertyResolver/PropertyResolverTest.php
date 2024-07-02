<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Cache\PropertyResolver;

use Doctrine\Persistence\Mapping\ClassMetadata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle2\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle2\Attribute\Target\ForProperties;
use Sofascore\PurgatoryBundle2\Cache\PropertyResolver\PropertyResolver;
use Sofascore\PurgatoryBundle2\Cache\RouteMetadata\RouteMetadata;
use Sofascore\PurgatoryBundle2\Cache\Subscription\PurgeSubscription;
use Symfony\Component\Routing\Route;

#[CoversClass(PropertyResolver::class)]
final class PropertyResolverTest extends TestCase
{
    public function testResolveField(): void
    {
        $resolver = new PropertyResolver();

        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->method('hasField')
            ->with('fooProperty')
            ->willReturn(true);

        $purgeSubscriptions = $resolver->resolveSubscription(
            routeMetadata: new RouteMetadata(
                routeName: 'route_foo',
                route: new Route('/foo'),
                purgeOn: new PurgeOn(
                    class: 'FooEntity',
                    target: new ForProperties(['fooProperty']),
                ),
                reflectionMethod: $this->createMock(\ReflectionMethod::class),
            ),
            classMetadata: $classMetadata,
            routeParams: [],
            target: 'fooProperty',
        );

        /** @var PurgeSubscription[] $subscriptions */
        $subscriptions = [...$purgeSubscriptions];

        self::assertTrue($purgeSubscriptions->getReturn());

        self::assertContainsOnlyInstancesOf(PurgeSubscription::class, $subscriptions);
        self::assertCount(1, $subscriptions);

        self::assertSame('fooProperty', $subscriptions[0]->property);
        self::assertSame('FooEntity', $subscriptions[0]->class);
    }

    public function testTargetNotField(): void
    {
        $resolver = new PropertyResolver();

        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->method('hasField')
            ->with('fooProperty')
            ->willReturn(false);

        $purgeSubscriptions = $resolver->resolveSubscription(
            routeMetadata: new RouteMetadata(
                routeName: 'route_foo',
                route: new Route('/foo'),
                purgeOn: new PurgeOn(
                    class: 'FooEntity',
                    target: new ForProperties(['fooProperty']),
                ),
                reflectionMethod: $this->createMock(\ReflectionMethod::class),
            ),
            classMetadata: $classMetadata,
            routeParams: [],
            target: 'fooProperty',
        );

        $subscriptions = [...$purgeSubscriptions];

        self::assertFalse($purgeSubscriptions->getReturn());

        self::assertCount(0, $subscriptions);
    }

    public function testResolveAssociation(): void
    {
        $resolver = new PropertyResolver();

        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->method('hasField')
            ->with('fooProperty')
            ->willReturn(false);
        $classMetadata->method('hasAssociation')
            ->with('fooProperty')
            ->willReturn(true);
        $classMetadata->method('isSingleValuedAssociation')
            ->with('fooProperty')
            ->willReturn(true);
        $classMetadata->method('isAssociationInverseSide')
            ->with('fooProperty')
            ->willReturn(false);

        $purgeSubscriptions = $resolver->resolveSubscription(
            routeMetadata: new RouteMetadata(
                routeName: 'route_foo',
                route: new Route('/foo'),
                purgeOn: new PurgeOn(
                    class: 'FooEntity',
                    target: new ForProperties(['fooProperty']),
                ),
                reflectionMethod: $this->createMock(\ReflectionMethod::class),
            ),
            classMetadata: $classMetadata,
            routeParams: [],
            target: 'fooProperty',
        );

        /** @var PurgeSubscription[] $subscriptions */
        $subscriptions = [...$purgeSubscriptions];

        self::assertTrue($purgeSubscriptions->getReturn());

        self::assertContainsOnlyInstancesOf(PurgeSubscription::class, $subscriptions);
        self::assertCount(1, $subscriptions);

        self::assertSame('fooProperty', $subscriptions[0]->property);
        self::assertSame('FooEntity', $subscriptions[0]->class);
    }
}
