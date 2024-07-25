<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Cache\PropertyResolver;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle\Attribute\Target\ForProperties;
use Sofascore\PurgatoryBundle\Cache\PropertyResolver\PropertyResolver;
use Sofascore\PurgatoryBundle\Cache\RouteMetadata\RouteMetadata;
use Sofascore\PurgatoryBundle\Cache\Subscription\PurgeSubscription;
use Sofascore\PurgatoryBundle\Tests\Cache\PropertyResolver\Fixtures\DummyChildEntity;
use Sofascore\PurgatoryBundle\Tests\Cache\PropertyResolver\Fixtures\DummyEntity;
use Symfony\Component\Routing\Route;

#[CoversClass(PropertyResolver::class)]
final class PropertyResolverTest extends TestCase
{
    public function testResolveField(): void
    {
        $resolver = new PropertyResolver(
            managerRegistry: $this->createMock(ManagerRegistry::class),
        );

        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->subClasses = [];
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
        $resolver = new PropertyResolver(
            managerRegistry: $this->createMock(ManagerRegistry::class),
        );

        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->subClasses = [];
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
        $resolver = new PropertyResolver(
            managerRegistry: $this->createMock(ManagerRegistry::class),
        );

        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->subClasses = [];
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

    public function testResolveFieldFromSubClass(): void
    {
        $subClassMetadata = $this->createMock(ClassMetadata::class);
        $subClassMetadata->expects(self::once())
            ->method('hasField')
            ->with('fooProperty')
            ->willReturn(true);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())
            ->method('getClassMetadata')
            ->with('FooSubEntity')
            ->willReturn($subClassMetadata);

        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $managerRegistry->expects(self::once())
            ->method('getManagerForClass')
            ->with('FooSubEntity')
            ->willReturn($entityManager);

        $resolver = new PropertyResolver(
            managerRegistry: $managerRegistry,
        );

        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->subClasses = ['FooSubEntity'];
        $classMetadata->expects(self::once())
            ->method('hasField')
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

    public function testResolveAssociationFromSubClass(): void
    {
        $subClassMetadata = $this->createMock(ClassMetadata::class);

        $subClassMetadata->expects(self::once())
            ->method('hasField')
            ->with('fooProperty')
            ->willReturn(false);

        $subClassMetadata->expects(self::once())
            ->method('hasAssociation')
            ->with('fooProperty')
            ->willReturn(true);

        $subClassMetadata->expects(self::once())
            ->method('isSingleValuedAssociation')
            ->with('fooProperty')
            ->willReturn(true);

        $subClassMetadata->expects(self::once())
            ->method('isAssociationInverseSide')
            ->with('fooProperty')
            ->willReturn(false);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())
            ->method('getClassMetadata')
            ->with('FooSubEntity')
            ->willReturn($subClassMetadata);

        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $managerRegistry->expects(self::once())
            ->method('getManagerForClass')
            ->with('FooSubEntity')
            ->willReturn($entityManager);

        $resolver = new PropertyResolver(
            managerRegistry: $managerRegistry,
        );

        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->subClasses = ['FooSubEntity'];
        $classMetadata->expects(self::once())
            ->method('hasField')
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

    public function testResolveFieldFromChildOfMappedSuperclass(): void
    {
        $subClassMetadata = $this->createMock(ClassMetadata::class);
        $subClassMetadata->expects(self::once())
            ->method('getName')
            ->willReturn(DummyChildEntity::class);
        $subClassMetadata->expects(self::exactly(2))
            ->method('hasField')
            ->with('foo')
            ->willReturn(true);

        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->expects(self::once())
            ->method('getAllMetadata')
            ->willReturn([$subClassMetadata]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())
            ->method('getMetadataFactory')
            ->willReturn($metadataFactory);

        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $managerRegistry->expects(self::once())
            ->method('getManagers')
            ->willReturn([$entityManager]);

        $resolver = new PropertyResolver(
            managerRegistry: $managerRegistry,
        );

        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata
            ->method('getName')
            ->willReturn(DummyEntity::class);
        $classMetadata->expects(self::exactly(2))
            ->method('hasField')
            ->with('foo')
            ->willReturn(false);
        $classMetadata->expects(self::exactly(2))
            ->method('hasAssociation')
            ->with('foo')
            ->willReturn(false);
        $classMetadata->subClasses = [];
        $classMetadata->isMappedSuperclass = true;

        for ($i = 0; $i < 2; ++$i) {
            $purgeSubscriptions = $resolver->resolveSubscription(
                routeMetadata: new RouteMetadata(
                    routeName: 'route_foo',
                    route: new Route('/foo'),
                    purgeOn: new PurgeOn(
                        class: DummyEntity::class,
                        target: new ForProperties(['foo']),
                    ),
                    reflectionMethod: $this->createMock(\ReflectionMethod::class),
                ),
                classMetadata: $classMetadata,
                routeParams: [],
                target: 'foo',
            );

            /** @var PurgeSubscription[] $subscriptions */
            $subscriptions = [...$purgeSubscriptions];

            self::assertTrue($purgeSubscriptions->getReturn());

            self::assertContainsOnlyInstancesOf(PurgeSubscription::class, $subscriptions);
            self::assertCount(1, $subscriptions);

            self::assertSame('foo', $subscriptions[0]->property);
            self::assertSame(DummyEntity::class, $subscriptions[0]->class);
        }
    }
}
