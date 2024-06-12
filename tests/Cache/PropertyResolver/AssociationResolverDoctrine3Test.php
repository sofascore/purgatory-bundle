<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Cache\PropertyResolver;

use Doctrine\ORM\Mapping\AssociationMapping;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\InverseSideMapping;
use Doctrine\ORM\Mapping\ManyToManyInverseSideMapping;
use Doctrine\ORM\Mapping\ManyToManyOwningSideMapping;
use Doctrine\ORM\Mapping\ManyToOneAssociationMapping;
use Doctrine\ORM\Mapping\OneToManyAssociationMapping;
use Doctrine\ORM\Mapping\OneToOneInverseSideMapping;
use Doctrine\ORM\Mapping\OneToOneOwningSideMapping;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle2\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle2\Attribute\Target\ForProperties;
use Sofascore\PurgatoryBundle2\Cache\Metadata\ControllerMetadata;
use Sofascore\PurgatoryBundle2\Cache\Metadata\PurgeSubscription;
use Sofascore\PurgatoryBundle2\Cache\PropertyResolver\AssociationResolver;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\PropertyInfo\PropertyReadInfo;
use Symfony\Component\PropertyInfo\PropertyReadInfoExtractorInterface;
use Symfony\Component\Routing\Route;

#[CoversClass(AssociationResolver::class)]
#[RequiresMethod(AssociationMapping::class, '__construct')]
final class AssociationResolverDoctrine3Test extends TestCase
{
    /**
     * @param array{class: class-string<AssociationMapping>, data: array} $associationMapping
     */
    #[DataProvider('associationProvider')]
    public function testResolveAssociations(
        bool $hasAssociation,
        array $associationMapping,
        bool $isGetAssociationMappedByTargetFieldCalled,
        array $additionalMappingProperties = [],
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

        /** @var AssociationMapping $association */
        $association = new $associationMapping['class'](...$associationMapping['data']);
        foreach ($additionalMappingProperties as $property => $value) {
            $association->{$property} = $value;
        }

        $resolver = new AssociationResolver($extractor);

        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->method('hasAssociation')
            ->with('fooProperty')
            ->willReturn($hasAssociation);
        $classMetadata->method('getAssociationMapping')
            ->with('fooProperty')
            ->willReturn($association);
        $classMetadata->method('isAssociationInverseSide')
            ->with('fooProperty')
            ->willReturn($association instanceof InverseSideMapping);

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
                'param1' => 'bazProperty',
                'param2' => '@const',
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
        self::assertSame(['barProperty.bazProperty'], $subscription[0]->routeParams['param1']);
        self::assertSame(['@const'], $subscription[0]->routeParams['param2']);
        self::assertSame('obj.getFoo().isActive() === true', (string) $subscription[0]->if);
    }

    public static function associationProvider(): iterable
    {
        $associationMapping = [
            'fieldName' => 'fooProperty',
            'sourceEntity' => 'FooEntity',
            'targetEntity' => 'BarEntity',
        ];

        yield 'OneToOne inverse mapping' => [
            'hasAssociation' => true,
            'associationMapping' => [
                'class' => OneToOneInverseSideMapping::class,
                'data' => $associationMapping,
            ],
            'isGetAssociationMappedByTargetFieldCalled' => true,
        ];

        yield 'OneToOne owning mapping' => [
            'hasAssociation' => true,
            'associationMapping' => [
                'class' => OneToOneOwningSideMapping::class,
                'data' => $associationMapping,
            ],
            'isGetAssociationMappedByTargetFieldCalled' => false,
            'additionalMappingProperties' => [
                'inversedBy' => 'barProperty',
            ],
        ];

        yield 'OneToMany mapping' => [
            'hasAssociation' => true,
            'associationMapping' => [
                'class' => OneToManyAssociationMapping::class,
                'data' => $associationMapping,
            ],
            'isGetAssociationMappedByTargetFieldCalled' => true,
        ];
    }

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

    /**
     * @param class-string<AssociationMapping> $associationMappingClass
     */
    #[TestWith([ManyToOneAssociationMapping::class])]
    #[TestWith([ManyToManyInverseSideMapping::class])]
    #[TestWith([ManyToManyOwningSideMapping::class])]
    public function testInvalidAssociationType(string $associationMappingClass): void
    {
        $resolver = new AssociationResolver(
            $this->createMock(PropertyReadInfoExtractorInterface::class),
        );

        $associationMapping = new $associationMappingClass(
            fieldName: 'fooProperty',
            sourceEntity: 'FooEntity',
            targetEntity: 'BarEntity',
        );

        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->expects(self::once())
            ->method('hasAssociation')
            ->with('fooProperty')
            ->willReturn(true);
        $classMetadata->expects(self::once())
            ->method('getAssociationMapping')
            ->with('fooProperty')
            ->willReturn($associationMapping);

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
}
