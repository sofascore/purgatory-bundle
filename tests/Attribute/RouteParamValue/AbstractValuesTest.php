<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Attribute\RouteParamValue;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\AbstractValues;

#[CoversClass(AbstractValues::class)]
final class AbstractValuesTest extends TestCase
{
    public function testToArray(): void
    {
        $values = new class() extends AbstractValues {
            public function getValues(): array
            {
                return ['foo', 'bar', 'baz'];
            }

            public static function type(): string
            {
                return 'foo-type';
            }
        };

        self::assertSame([
            'type' => 'foo-type',
            'values' => ['foo', 'bar', 'baz'],
        ], $values->toArray());
    }
}
