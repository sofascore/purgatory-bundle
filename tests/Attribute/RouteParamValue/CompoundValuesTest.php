<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Attribute\RouteParamValue;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\CompoundValues;
use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\DynamicValues;
use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\EnumValues;
use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\PropertyValues;
use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\RawValues;
use Sofascore\PurgatoryBundle2\Exception\InvalidArgumentException;
use Sofascore\PurgatoryBundle2\Tests\Fixtures\DummyIntEnum;
use Symfony\Component\HttpKernel\Kernel;

#[CoversClass(CompoundValues::class)]
final class CompoundValuesTest extends TestCase
{
    #[TestWith([['foo'], [new PropertyValues('foo')]])]
    #[TestWith([[['foo', 'bar']], [new PropertyValues('foo', 'bar')]])]
    #[TestWith([[new RawValues('foo', 'bar')], [new RawValues('foo', 'bar')]])]
    #[TestWith([['foo', new RawValues('foo', 'bar')], [new PropertyValues('foo'), new RawValues('foo', 'bar')]])]
    public function testValueNormalization(mixed $values, mixed $expectedValues): void
    {
        $compoundValues = new CompoundValues(...$values);

        self::assertEquals($expectedValues, $compoundValues->getValues());
    }

    public function testExceptionIsThrownOnSelf(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('An argument cannot be an instance of "%s".', CompoundValues::class));

        new CompoundValues(new CompoundValues('foo'));
    }

    public function testToArray(): void
    {
        $compoundValues = new CompoundValues(['foo', 'bar'], new RawValues('baz', 'qux'));

        self::assertSame([
            'type' => CompoundValues::type(),
            'values' => [
                ['type' => PropertyValues::type(), 'values' => ['foo', 'bar']],
                ['type' => RawValues::type(), 'values' => ['baz', 'qux']],
            ],
        ], $compoundValues->toArray());
    }

    public function testBuildInverseValuesFor(): void
    {
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
                    arg: Kernel::MAJOR_VERSION > 5 ? 'association?.obj' : 'association.obj',
                ),
                new EnumValues(DummyIntEnum::class),
                new PropertyValues(
                    Kernel::MAJOR_VERSION > 5 ? 'association?.obj' : 'association.obj',
                ),
                new RawValues(1, null, 'str'),
            ),
            actual: $compoundValues->buildInverseValuesFor('association'),
        );
    }
}
