<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Attribute\RouteParamValue;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\EnumValues;
use Sofascore\PurgatoryBundle2\Tests\Fixtures\DummyStringEnum;

#[CoversClass(EnumValues::class)]
final class EnumValuesTest extends TestCase
{
    public function testValueNormalization(): void
    {
        $enumValues = new EnumValues(DummyStringEnum::class);

        self::assertSame([DummyStringEnum::class], $enumValues->getValues());
    }
}
