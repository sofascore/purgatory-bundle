<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Cache\PropertyResolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\CompoundValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\DynamicValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\EnumValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\PropertyValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\RawValues;
use Sofascore\PurgatoryBundle\Cache\PropertyResolver\InverseValuesBuilder\CompoundInverseValuesBuilder;
use Sofascore\PurgatoryBundle\Cache\PropertyResolver\InverseValuesBuilder\DynamicInverseValuesBuilder;
use Sofascore\PurgatoryBundle\Cache\PropertyResolver\InverseValuesBuilder\PropertyInverseValuesBuilder;
use Sofascore\PurgatoryBundle\Tests\Fixtures\DummyIntEnum;
use Symfony\Component\DependencyInjection\ServiceLocator;

#[CoversClass(CompoundInverseValuesBuilder::class)]
#[CoversClass(DynamicInverseValuesBuilder::class)]
#[CoversClass(PropertyInverseValuesBuilder::class)]
final class InverseValuesBuildersTest extends TestCase
{
    public function testBuild(): void
    {
        $inverseValuesBuilder = new CompoundInverseValuesBuilder(new ServiceLocator([
            DynamicValues::type() => static fn () => new DynamicInverseValuesBuilder(),
            PropertyValues::type() => static fn () => new PropertyInverseValuesBuilder(),
        ]));

        $compoundValues = new CompoundValues(
            new DynamicValues('alias'),
            new DynamicValues('alias', arg: 'obj'),
            new EnumValues(DummyIntEnum::class),
            new PropertyValues('obj'),
            new RawValues(1, null, 'str'),
        );

        self::assertEquals(
            expected: new CompoundValues(
                new DynamicValues('alias', arg: 'association'),
                new DynamicValues(
                    alias: 'alias',
                    arg: 'association?.obj',
                ),
                new EnumValues(DummyIntEnum::class),
                new PropertyValues('association?.obj'),
                new RawValues(1, null, 'str'),
            ),
            actual: $inverseValuesBuilder->build($compoundValues, \stdClass::class, 'association'),
        );
    }
}
