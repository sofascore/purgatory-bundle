<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Cache\PropertyResolver;

use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresFunction;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Sofascore\PurgatoryBundle\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\PropertyValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\RawValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\ValuesInterface;
use Sofascore\PurgatoryBundle\Attribute\Target\ForProperties;
use Sofascore\PurgatoryBundle\Cache\PropertyResolver\AssociationResolver;
use Sofascore\PurgatoryBundle\Cache\PropertyResolver\ExpressionLanguage\InverseRelationExpressionTransformer;
use Sofascore\PurgatoryBundle\Cache\PropertyResolver\InverseValuesBuilder\PropertyInverseValuesBuilder;
use Sofascore\PurgatoryBundle\Cache\RouteMetadata\RouteMetadata;
use Sofascore\PurgatoryBundle\Cache\Subscription\PurgeSubscription;
use Sofascore\PurgatoryBundle\Exception\AccessorNotInferableException;
use Symfony\Component\DependencyInjection\ServiceLocator;
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
        $extractor->expects(self::once())
            ->method('getReadInfo')
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

        $purgeSubscription = $this->resolveSubscription(
            extractor: $extractor,
            associationMapping: $associationMapping,
            isGetAssociationMappedByTargetFieldCalled: $isGetAssociationMappedByTargetFieldCalled,
            isAssociationInverseSide: $isAssociationInverseSide,
            if: new Expression('obj.isActive() === true'),
            routeParams: [
                'param1' => new PropertyValues('bazProperty'),
                'param2' => new RawValues('const'),
            ],
        );

        /** @var PurgeSubscription[] $subscription */
        $subscription = [...$purgeSubscription];

        self::assertTrue($purgeSubscription->getReturn());

        self::assertContainsOnlyInstancesOf(PurgeSubscription::class, $subscription);
        self::assertCount(1, $subscription);

        self::assertNull($subscription[0]->property);
        self::assertSame('BarEntity', $subscription[0]->class);
        self::assertEquals(
            new PropertyValues('barProperty?.bazProperty'),
            $subscription[0]->routeParams['param1'],
        );
        self::assertEquals(new RawValues('const'), $subscription[0]->routeParams['param2']);
        self::assertSame('obj.getFoo() !== null ? (obj.getFoo().isActive() === true) : false', (string) $subscription[0]->if);
    }

    abstract public static function associationProvider(): iterable;

    #[DataProvider('associationProvider')]
    #[RequiresFunction('deepclone_to_array')]
    public function testResolveAssociationsWithClosureIf(
        array $associationMapping,
        bool $isGetAssociationMappedByTargetFieldCalled,
        bool $isAssociationInverseSide,
    ): void {
        $extractor = $this->createMock(PropertyReadInfoExtractorInterface::class);
        $extractor->expects(self::once())
            ->method('getReadInfo')
            ->with('BarEntity', 'barProperty')
            ->willReturn(
                new PropertyReadInfo(
                    type: PropertyReadInfo::TYPE_METHOD,
                    name: 'getBarProperty',
                    visibility: PropertyReadInfo::VISIBILITY_PUBLIC,
                    static: false,
                    byRef: false,
                ),
            );

        $purgeSubscription = $this->resolveSubscription(
            extractor: $extractor,
            associationMapping: $associationMapping,
            isGetAssociationMappedByTargetFieldCalled: $isGetAssociationMappedByTargetFieldCalled,
            isAssociationInverseSide: $isAssociationInverseSide,
            if: $if = static fn (object $entity): bool => true,
        );

        /** @var PurgeSubscription[] $subscriptions */
        $subscriptions = [...$purgeSubscription];

        self::assertTrue($purgeSubscription->getReturn());

        self::assertCount(1, $subscriptions);
        self::assertSame('BarEntity', $subscriptions[0]->class);
        self::assertSame($if, $subscriptions[0]->if);
        self::assertSame('barProperty', $subscriptions[0]->inversePropertyPath);
    }

    #[DataProvider('associationProvider')]
    #[RequiresFunction('deepclone_to_array')]
    public function testClosureIfThrowsWhenInversePropertyIsNotReadable(
        array $associationMapping,
        bool $isGetAssociationMappedByTargetFieldCalled,
        bool $isAssociationInverseSide,
    ): void {
        $extractor = $this->createMock(PropertyReadInfoExtractorInterface::class);
        $extractor->expects(self::once())
            ->method('getReadInfo')
            ->with('BarEntity', 'barProperty')
            ->willReturn(null);

        $this->expectException(AccessorNotInferableException::class);

        [...$this->resolveSubscription(
            extractor: $extractor,
            associationMapping: $associationMapping,
            isGetAssociationMappedByTargetFieldCalled: $isGetAssociationMappedByTargetFieldCalled,
            isAssociationInverseSide: $isAssociationInverseSide,
            if: static fn (object $entity): bool => true,
        )];
    }

    public function testFieldNotAssociation(): void
    {
        $resolver = new AssociationResolver(
            self::createStub(ContainerInterface::class),
            self::createStub(PropertyReadInfoExtractorInterface::class),
            new InverseRelationExpressionTransformer(
                self::createStub(PropertyReadInfoExtractorInterface::class),
            ),
        );

        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->expects(self::once())
            ->method('hasAssociation')
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
                reflectionMethod: self::createStub(\ReflectionMethod::class),
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
            self::createStub(ContainerInterface::class),
            self::createStub(PropertyReadInfoExtractorInterface::class),
            new InverseRelationExpressionTransformer(
                self::createStub(PropertyReadInfoExtractorInterface::class),
            ),
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
            routeMetadata: new RouteMetadata(
                routeName: 'route_foo',
                route: new Route('/foo'),
                purgeOn: new PurgeOn(
                    class: 'FooEntity',
                    target: new ForProperties(['fooProperty']),
                ),
                reflectionMethod: self::createStub(\ReflectionMethod::class),
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

    /**
     * @param array<string, ValuesInterface> $routeParams
     *
     * @return \Generator<int, PurgeSubscription, mixed, bool>
     */
    private function resolveSubscription(
        PropertyReadInfoExtractorInterface $extractor,
        array $associationMapping,
        bool $isGetAssociationMappedByTargetFieldCalled,
        bool $isAssociationInverseSide,
        \Closure|Expression $if,
        array $routeParams = [],
    ): \Generator {
        $resolver = new AssociationResolver(
            new ServiceLocator([
                PropertyValues::type() => static fn () => new PropertyInverseValuesBuilder(),
            ]),
            $extractor,
            new InverseRelationExpressionTransformer($extractor),
        );

        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->expects(self::once())
            ->method('hasAssociation')
            ->with('fooProperty')
            ->willReturn(true);
        // only called for OneToOne associations
        $classMetadata->expects(self::atMost(1))
            ->method('isAssociationInverseSide')
            ->with('fooProperty')
            ->willReturn($isAssociationInverseSide);
        $classMetadata->expects(self::once())
            ->method('getAssociationMapping')
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

        $classMetadata->expects(self::once())
            ->method('getAssociationTargetClass')
            ->with('fooProperty')
            ->willReturn('BarEntity');

        return $resolver->resolveSubscription(
            routeMetadata: new RouteMetadata(
                routeName: 'route_foo',
                route: new Route('/foo/{param1}/{param2}'),
                purgeOn: new PurgeOn(
                    class: 'FooEntity',
                    target: new ForProperties(['fooProperty']),
                    if: $if,
                ),
                reflectionMethod: self::createStub(\ReflectionMethod::class),
            ),
            classMetadata: $classMetadata,
            routeParams: $routeParams,
            target: 'fooProperty',
        );
    }
}
