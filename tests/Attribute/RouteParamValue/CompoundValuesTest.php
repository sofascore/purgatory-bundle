<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Attribute\RouteParamValue;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\CompoundValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\PropertyValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\RawValues;
use Sofascore\PurgatoryBundle\Exception\InvalidArgumentException;

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
}
