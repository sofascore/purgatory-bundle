<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Cache\PropertyResolver;

use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle2\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\PropertyValues;
use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\RawValues;
use Sofascore\PurgatoryBundle2\Attribute\Target\ForProperties;
use Sofascore\PurgatoryBundle2\Cache\ControllerMetadata\ControllerMetadata;
use Sofascore\PurgatoryBundle2\Cache\PropertyResolver\AssociationResolver;
use Sofascore\PurgatoryBundle2\Cache\Subscription\PurgeSubscription;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\PropertyInfo\PropertyReadInfo;
use Symfony\Component\PropertyInfo\PropertyReadInfoExtractorInterface;
use Symfony\Component\Routing\Route;

abstract class AssociationResolverTestCase extends TestCase
{
    abstract protected function createAssociationMapping(array $associationMappingConfig): mixed;

    #[DataProvider('associationProvider')]
    public function testResolveAssociations(
        array $associationMapping,
        bool $isGetAssociationMappedByTargetFieldCalled,
        bool $isAssociationInverseSide,
    ): void {
        $extractor = $this->createMock(PropertyReadInfoExtractorInterface::class);
        $extractor->method('getReadInfo')
            ->with('BarEntity', 'barProperty')
            ->willReturn(
                new PropertyReadInfo(
                    type: PropertyReadInfo::TYPE_METHOD,
                    name: 'getFoo',
                    visibility: PropertyReadInfo::VISIBILITY_PUBLIC,
                    static: false,
                    byRef: false,
                ),
            );

        $resolver = new AssociationResolver($extractor);

        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->method('hasAssociation')
            ->with('fooProperty')
            ->willReturn(true);
        $classMetadata->method('isAssociationInverseSide')
            ->with('fooProperty')
            ->willReturn($isAssociationInverseSide);
        $classMetadata->method('getAssociationMapping')
            ->with('fooProperty')
            ->willReturn($this->createAssociationMapping($associationMapping));

        if ($isGetAssociationMappedByTargetFieldCalled) {
            $classMetadata->expects(self::once())
                ->method('getAssociationMappedByTargetField')
                ->with('fooProperty')
                ->willReturn('barProperty');
        } else {
            $classMetadata->expects(self::never())
                ->method('getAssociationMappedByTargetField');
        }

        $classMetadata->method('getAssociationTargetClass')
            ->with('fooProperty')
            ->willReturn('BarEntity');

        $purgeSubscription = $resolver->resolveSubscription(
            controllerMetadata: new ControllerMetadata(
                routeName: 'route_foo',
                route: new Route('/foo/{param1}/{param2}'),
                purgeOn: new PurgeOn(
                    class: 'FooEntity',
                    target: new ForProperties(['fooProperty']),
                    if: new Expression('obj.isActive() === true'),
                ),
                reflectionMethod: $this->createMock(\ReflectionMethod::class),
            ),
            classMetadata: $classMetadata,
            routeParams: [
                'param1' => new PropertyValues('bazProperty'),
                'param2' => new RawValues('const'),
            ],
            target: 'fooProperty',
        );

        /** @var PurgeSubscription[] $subscription */
        $subscription = [...$purgeSubscription];

        self::assertTrue($purgeSubscription->getReturn());

        self::assertContainsOnlyInstancesOf(PurgeSubscription::class, $subscription);
        self::assertCount(1, $subscription);

        self::assertNull($subscription[0]->property);
        self::assertSame('BarEntity', $subscription[0]->class);
        self::assertEquals(new PropertyValues('barProperty.bazProperty'), $subscription[0]->routeParams['param1']);
        self::assertEquals(new RawValues('const'), $subscription[0]->routeParams['param2']);
        self::assertSame('obj.getFoo().isActive() === true', (string) $subscription[0]->if);
    }

    abstract public static function associationProvider(): iterable;

    public function testFieldNotAssociation(): void
    {
        $resolver = new AssociationResolver(
            $this->createMock(PropertyReadInfoExtractorInterface::class),
        );

        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->method('hasAssociation')
            ->with('fooProperty')
            ->willReturn(false);

        $purgeSubscriptions = $resolver->resolveSubscription(
            controllerMetadata: new ControllerMetadata(
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

    #[DataProvider('invalidAssociationProvider')]
    public function testInvalidAssociationType(array $associationMapping): void
    {
        $resolver = new AssociationResolver(
            $this->createMock(PropertyReadInfoExtractorInterface::class),
        );

        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->expects(self::once())
            ->method('hasAssociation')
            ->with('fooProperty')
            ->willReturn(true);
        $classMetadata->expects(self::once())
            ->method('getAssociationMapping')
            ->with('fooProperty')
            ->willReturn($this->createAssociationMapping($associationMapping));

        $purgeSubscriptions = $resolver->resolveSubscription(
            controllerMetadata: new ControllerMetadata(
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

    abstract public static function invalidAssociationProvider(): iterable;
}
