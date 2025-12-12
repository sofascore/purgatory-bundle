<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Attribute\RouteParamValue;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\EnumValues;
use Sofascore\PurgatoryBundle\Exception\InvalidArgumentException;

final class EnumValuesTest extends TestCase
{
    /**
     * @param class-string $class
     */
    #[TestWith([\stdClass::class])]
    #[TestWith([SomeUnitEnum::class])]
    public function testExceptionIsThrownWhenClassIsNotABackedEnum(string $class): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The argument must be a backed enum.');

        new EnumValues($class);
    }
}

enum SomeUnitEnum
{
    case One;
    case Two;
}
