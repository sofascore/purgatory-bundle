<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle2\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle2\Attribute\Target\ForProperties;

#[CoversClass(PurgeOn::class)]
final class PurgeOnTest extends TestCase
{
    #[TestWith(['target', 'foo', new ForProperties('foo')])]
    #[TestWith(['target', ['foo', 'bar'], new ForProperties(['foo', 'bar'])])]
    #[TestWith(['route', 'foo', ['foo']])]
    public function testValueNormalization(string $property, mixed $value, mixed $expectedValue): void
    {
        $purgeOn = new PurgeOn(
            \stdClass::class,
            ...[$property => $value],
        );

        self::assertEquals($expectedValue, $purgeOn->$property);
    }
}
