<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Cache\PropertyResolver;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\ClassMetadata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle\Attribute\Target\ForProperties;
use Sofascore\PurgatoryBundle\Cache\PropertyResolver\MethodResolver;
use Sofascore\PurgatoryBundle\Cache\PropertyResolver\PropertyResolver;
use Sofascore\PurgatoryBundle\Cache\RouteMetadata\RouteMetadata;
use Sofascore\PurgatoryBundle\Cache\Subscription\PurgeSubscription;
use Sofascore\PurgatoryBundle\Tests\Cache\PropertyResolver\Fixtures\DummyEntity;
use Symfony\Component\PropertyInfo\PropertyReadInfo;
use Symfony\Component\PropertyInfo\PropertyReadInfoExtractorInterface;
use Symfony\Component\Routing\Route;

#[CoversClass(MethodResolver::class)]
final class MethodResolverTest extends TestCase
{
    #[TestWith(['foo', 1])]
    #[TestWith(['getFoo', 0])]
    public function testResolveMethod(string $target, int $invocationCount): void
    {
        $propertyReadInfoExtractor = $this->createMock(PropertyReadInfoExtractorInterface::class);
        $propertyReadInfoExtractor->expects(self::exactly($invocationCount))
            ->method('getReadInfo')
            ->with(DummyEntity::class, 'foo')
            ->willReturn(new PropertyReadInfo(
                PropertyReadInfo::TYPE_METHOD,
                'getFoo',
                PropertyReadInfo::VISIBILITY_PUBLIC,
                false,
                false,
            ));

        $resolver = new MethodResolver(
            [new PropertyResolver($this->createMock(ManagerRegistry::class))],
            $propertyReadInfoExtractor,
        );

        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->method('hasField')
            ->willReturnCallback(fn (string $property) => match ($property) {
                'bar' => true,
                'baz' => true,
            });

        $purgeSubscriptions = $resolver->resolveSubscription(
            routeMetadata: new RouteMetadata(
                routeName: 'route_foo',
                route: new Route('/foo'),
                purgeOn: new PurgeOn(
                    class: DummyEntity::class,
                    target: new ForProperties([$target]),
                ),
                reflectionMethod: $this->createMock(\ReflectionMethod::class),
            ),
            classMetadata: $classMetadata,
            routeParams: [],
            target: $target,
        );

        /** @var PurgeSubscription[] $subscriptions */
        $subscriptions = [...$purgeSubscriptions];

        self::assertTrue($purgeSubscriptions->getReturn());

        self::assertContainsOnlyInstancesOf(PurgeSubscription::class, $subscriptions);
        self::assertCount(2, $subscriptions);

        self::assertSame('bar', $subscriptions[0]->property);
        self::assertSame(DummyEntity::class, $subscriptions[0]->class);

        self::assertSame('baz', $subscriptions[1]->property);
        self::assertSame(DummyEntity::class, $subscriptions[1]->class);
    }

    public function testTargetNotMethod(): void
    {
        $propertyReadInfoExtractor = $this->createMock(PropertyReadInfoExtractorInterface::class);
        $propertyReadInfoExtractor->expects(self::once())
            ->method('getReadInfo')
            ->with(DummyEntity::class, 'bar')
            ->willReturn(new PropertyReadInfo(
                PropertyReadInfo::TYPE_PROPERTY,
                'bar',
                PropertyReadInfo::VISIBILITY_PUBLIC,
                false,
                false,
            ));

        $resolver = new MethodResolver(
            [],
            $propertyReadInfoExtractor,
        );

        $classMetadata = $this->createMock(ClassMetadata::class);

        $purgeSubscriptions = $resolver->resolveSubscription(
            routeMetadata: new RouteMetadata(
                routeName: 'route_foo',
                route: new Route('/foo'),
                purgeOn: new PurgeOn(
                    class: DummyEntity::class,
                    target: new ForProperties(['bar']),
                ),
                reflectionMethod: $this->createMock(\ReflectionMethod::class),
            ),
            classMetadata: $classMetadata,
            routeParams: [],
            target: 'bar',
        );

        /** @var PurgeSubscription[] $subscriptions */
        $subscriptions = [...$purgeSubscriptions];

        self::assertFalse($purgeSubscriptions->getReturn());

        self::assertCount(0, $subscriptions);
    }
}
