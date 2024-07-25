<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Cache\PropertyResolver;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresMethod;
use Sofascore\PurgatoryBundle\Cache\PropertyResolver\AssociationResolver;

#[CoversClass(AssociationResolver::class)]
#[RequiresMethod(ClassMetadataInfo::class, '__construct')]
final class AssociationResolverDoctrine2Test extends AssociationResolverTestCase
{
    /**
     * @param array{type: ClassMetadata::ONE_TO_*|ClassMetadata::MANY_TO_*, inversedBy?: string} $associationMappingConfig
     *
     * @return array{type: ClassMetadata::ONE_TO_*|ClassMetadata::MANY_TO_*, inversedBy?: string}
     */
    protected function createAssociationMapping(array $associationMappingConfig): array
    {
        return $associationMappingConfig;
    }

    public static function associationProvider(): iterable
    {
        yield 'OneToOne inverse mapping' => [
            'associationMapping' => [
                'type' => ClassMetadata::ONE_TO_ONE,
            ],
            'isGetAssociationMappedByTargetFieldCalled' => true,
            'isAssociationInverseSide' => true,
        ];

        yield 'OneToOne owning mapping' => [
            'associationMapping' => [
                'type' => ClassMetadata::ONE_TO_ONE,
                'inversedBy' => 'barProperty',
            ],
            'isGetAssociationMappedByTargetFieldCalled' => false,
            'isAssociationInverseSide' => false,
        ];

        yield 'OneToMany mapping' => [
            'associationMapping' => [
                'type' => ClassMetadata::ONE_TO_MANY,
            ],
            'isGetAssociationMappedByTargetFieldCalled' => true,
            'isAssociationInverseSide' => true,
        ];
    }

    public static function invalidAssociationProvider(): iterable
    {
        yield [
            ['type' => ClassMetadata::MANY_TO_ONE],
        ];
        yield [
            ['type' => ClassMetadata::MANY_TO_MANY],
        ];
    }
}
