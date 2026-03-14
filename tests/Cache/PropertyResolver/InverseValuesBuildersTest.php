<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Cache\PropertyResolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\CompoundValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\DynamicValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\EnumValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\ExpressionValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\PropertyValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\RawValues;
use Sofascore\PurgatoryBundle\Cache\PropertyResolver\ExpressionLanguage\InverseRelationExpressionTransformer;
use Sofascore\PurgatoryBundle\Cache\PropertyResolver\InverseValuesBuilder\CompoundInverseValuesBuilder;
use Sofascore\PurgatoryBundle\Cache\PropertyResolver\InverseValuesBuilder\DynamicInverseValuesBuilder;
use Sofascore\PurgatoryBundle\Cache\PropertyResolver\InverseValuesBuilder\ExpressionInverseValuesBuilder;
use Sofascore\PurgatoryBundle\Cache\PropertyResolver\InverseValuesBuilder\PropertyInverseValuesBuilder;
use Sofascore\PurgatoryBundle\Tests\Fixtures\DummyIntEnum;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\PropertyInfo\PropertyReadInfo;
use Symfony\Component\PropertyInfo\PropertyReadInfoExtractorInterface;

#[CoversClass(CompoundInverseValuesBuilder::class)]
#[CoversClass(DynamicInverseValuesBuilder::class)]
#[CoversClass(PropertyInverseValuesBuilder::class)]
final class InverseValuesBuildersTest extends TestCase
{
    public function testBuild(): void
    {
        $extractor = $this->createMock(PropertyReadInfoExtractorInterface::class);
        $extractor->expects(self::once())
            ->method('getReadInfo')
            ->with(\stdClass::class, 'association')
            ->willReturn(
                new PropertyReadInfo(
                    type: PropertyReadInfo::TYPE_METHOD,
                    name: 'getAssociation',
                    visibility: PropertyReadInfo::VISIBILITY_PUBLIC,
                    static: false,
                    byRef: false,
                ),
            );

        $inverseValuesBuilder = new CompoundInverseValuesBuilder(new ServiceLocator([
            DynamicValues::type() => static fn () => new DynamicInverseValuesBuilder(),
            ExpressionValues::type() => static fn () => new ExpressionInverseValuesBuilder(
                new InverseRelationExpressionTransformer($extractor),
            ),
            PropertyValues::type() => static fn () => new PropertyInverseValuesBuilder(),
        ]));

        $compoundValues = new CompoundValues(
            new DynamicValues('alias'),
            new DynamicValues('alias', propertyPath: 'obj'),
            new EnumValues(DummyIntEnum::class),
            new PropertyValues('obj'),
            new RawValues(1, null, 'str'),
            new ExpressionValues('obj.firstName~"-"~obj.lastName'),
        );

        self::assertEquals(
            expected: new CompoundValues(
                new DynamicValues('alias', propertyPath: 'association'),
                new DynamicValues(
                    provider: 'alias',
                    propertyPath: 'association?.obj',
                ),
                new EnumValues(DummyIntEnum::class),
                new PropertyValues('association?.obj'),
                new RawValues(1, null, 'str'),
                new ExpressionValues(
                    'obj.getAssociation() !== null ? (obj.getAssociation().firstName~"-"~obj.getAssociation().lastName) : null',
                ),
            ),
            actual: $inverseValuesBuilder->build($compoundValues, \stdClass::class, 'association'),
        );
    }
}
