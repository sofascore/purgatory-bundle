<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Cache\PropertyResolver;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\EmbeddedClassMapping;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle2\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle2\Attribute\Target\ForProperties;
use Sofascore\PurgatoryBundle2\Cache\PropertyResolver\EmbeddableResolver;
use Sofascore\PurgatoryBundle2\Cache\RouteMetadata\RouteMetadata;
use Sofascore\PurgatoryBundle2\Cache\Subscription\PurgeSubscription;
use Sofascore\PurgatoryBundle2\Exception\EntityMetadataNotFoundException;
use Symfony\Component\Routing\Route;

/**
 * @final
 */
#[CoversClass(EmbeddableResolver::class)]
#[RequiresMethod(EmbeddedClassMapping::class, '__construct')]
class EmbeddableResolverDoctrine3Test extends TestCase
{
    public function testResolveEmbeddable(): void
    {
        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->expects(self::once())
            ->method('getName')
            ->willReturn('ParentClass');
        $classMetadata->embeddedClasses = $this->provideEmbeddedClasses([
            'foo' => [
                'class' => 'BarEntity',
            ],
        ]);

        $embeddableClassMetadata = $this->createMock(ClassMetadata::class);
        $embeddableClassMetadata->method('getFieldNames')
            ->willReturn(['foo', 'bar']);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())
            ->method('getClassMetadata')
            ->with('BarEntity')
            ->willReturn($embeddableClassMetadata);

        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $managerRegistry->method('getManagerForClass')
            ->with('ParentClass')
            ->willReturn($entityManager);

        $resolver = new EmbeddableResolver($managerRegistry);

        $purgeSubscriptions = $resolver->resolveSubscription(
            routeMetadata: new RouteMetadata(
                routeName: 'route_foo',
                route: new Route('/foo'),
                purgeOn: new PurgeOn(
                    class: 'FooEntity',
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
        self::assertCount(2, $subscriptions);

        self::assertSame('foo.foo', $subscriptions[0]->property);
        self::assertSame('FooEntity', $subscriptions[0]->class);

        self::assertSame('foo.bar', $subscriptions[1]->property);
        self::assertSame('FooEntity', $subscriptions[1]->class);
    }

    public function testEmbeddableMetadataNotFound(): void
    {
        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->expects(self::once())
            ->method('getName')
            ->willReturn('ParentClass');
        $classMetadata->embeddedClasses = $this->provideEmbeddedClasses([
            'foo' => [
                'class' => 'BarEntity',
            ],
        ]);

        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $managerRegistry->expects(self::once())
            ->method('getManagerForClass')
            ->with('ParentClass')
            ->willReturn(null);

        $resolver = new EmbeddableResolver($managerRegistry);

        $this->expectException(EntityMetadataNotFoundException::class);
        $this->expectExceptionMessage('Unable to retrieve metadata for entity "BarEntity".');

        [...$resolver->resolveSubscription(
            routeMetadata: new RouteMetadata(
                routeName: 'route_foo',
                route: new Route('/foo'),
                purgeOn: new PurgeOn(
                    class: 'FooEntity',
                    target: new ForProperties(['foo']),
                ),
                reflectionMethod: $this->createMock(\ReflectionMethod::class),
            ),
            classMetadata: $classMetadata,
            routeParams: [],
            target: 'foo',
        )];
    }

    protected function provideEmbeddedClasses(array $embeddedClasses): array
    {
        foreach ($embeddedClasses as $field => $embeddedClass) {
            $embeddedClasses[$field] = new EmbeddedClassMapping($embeddedClass['class']);
        }

        return $embeddedClasses;
    }
}
