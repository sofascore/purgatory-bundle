<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Cache\PropertyResolver;

use Doctrine\ORM\Mapping\AssociationMapping;
use Doctrine\ORM\Mapping\ManyToManyInverseSideMapping;
use Doctrine\ORM\Mapping\ManyToManyOwningSideMapping;
use Doctrine\ORM\Mapping\ManyToOneAssociationMapping;
use Doctrine\ORM\Mapping\OneToManyAssociationMapping;
use Doctrine\ORM\Mapping\OneToOneInverseSideMapping;
use Doctrine\ORM\Mapping\OneToOneOwningSideMapping;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresMethod;
use Sofascore\PurgatoryBundle\Cache\PropertyResolver\AssociationResolver;

#[CoversClass(AssociationResolver::class)]
#[RequiresMethod(AssociationMapping::class, '__construct')]
final class AssociationResolverDoctrine3Test extends AssociationResolverTestCase
{
    /**
     * @param array{class: class-string<AssociationMapping>, inversedBy?: string} $associationMappingConfig
     */
    protected function createAssociationMapping(array $associationMappingConfig): AssociationMapping
    {
        $associationMapping = new $associationMappingConfig['class'](
            fieldName: 'fooProperty',
            sourceEntity: 'FooEntity',
            targetEntity: 'BarEntity',
        );

        if (isset($associationMappingConfig['inversedBy'])) {
            $associationMapping->inversedBy = $associationMappingConfig['inversedBy'];
        }

        return $associationMapping;
    }

    public static function associationProvider(): iterable
    {
        yield 'OneToOne inverse mapping' => [
            'associationMapping' => [
                'class' => OneToOneInverseSideMapping::class,
            ],
            'isGetAssociationMappedByTargetFieldCalled' => true,
            'isAssociationInverseSide' => true,
        ];

        yield 'OneToOne owning mapping' => [
            'associationMapping' => [
                'class' => OneToOneOwningSideMapping::class,
                'inversedBy' => 'barProperty',
            ],
            'isGetAssociationMappedByTargetFieldCalled' => false,
            'isAssociationInverseSide' => false,
        ];

        yield 'OneToMany mapping' => [
            'associationMapping' => [
                'class' => OneToManyAssociationMapping::class,
            ],
            'isGetAssociationMappedByTargetFieldCalled' => true,
            'isAssociationInverseSide' => true,
        ];
    }

    public static function invalidAssociationProvider(): iterable
    {
        yield [
            ['class' => ManyToOneAssociationMapping::class],
        ];
        yield [
            ['class' => ManyToManyInverseSideMapping::class],
        ];
        yield [
            ['class' => ManyToManyOwningSideMapping::class],
        ];
    }
}
