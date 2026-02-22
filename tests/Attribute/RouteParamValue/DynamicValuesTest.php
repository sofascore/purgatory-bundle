<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Attribute\RouteParamValue;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\DynamicValues;

#[CoversClass(DynamicValues::class)]
final class DynamicValuesTest extends TestCase
{
    #[TestWith(['alias', 'alias'])]
    #[TestWith([[DummyStaticCallable::class, 'handle'], [DummyStaticCallable::class, 'handle']])]
    #[TestWith([DummyStaticCallable::class.'::handle', [DummyStaticCallable::class, 'handle']])]
    public function testValueNormalization(string|array $input, string|array $expected): void
    {
        self::assertSame($expected, (new DynamicValues($input))->provider);
    }

    public function testNonStaticStringCallableIsRejected(): void
    {
        $this->expectException(\ValueError::class);
        $this->expectExceptionMessage('Only static method callables are supported.');

        new DynamicValues(DummyInstanceCallable::class.'::handle');
    }

    public function testObjectCallableIsRejected(): void
    {
        $this->expectException(\ValueError::class);
        $this->expectExceptionMessage('Object callables are not supported.');

        new DynamicValues([new DummyInstanceCallable(), 'handle']);
    }
}

final class DummyStaticCallable
{
    public static function handle(): void
    {
    }
}

final class DummyInstanceCallable
{
    public function handle(): void
    {
    }
}
