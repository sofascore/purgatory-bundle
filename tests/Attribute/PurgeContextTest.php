<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Attribute\PurgeContext;
use Sofascore\PurgatoryBundle\Tests\Fixtures\DummyStringEnum;

#[CoversClass(PurgeContext::class)]
final class PurgeContextTest extends TestCase
{
    public function testContextIsKeptAsIs(): void
    {
        $context = [
            'varnish' => ['tags' => ['social'], 'ttl' => 60],
            'throttle' => 8.5,
            'enabled' => true,
            'reason' => null,
            'case' => DummyStringEnum::Case1,
        ];

        self::assertSame($context, (new PurgeContext($context))->context);
    }

    public function testContextWithObjectIsRejected(): void
    {
        $this->expectException(\ValueError::class);
        $this->expectExceptionMessage('The context may only contain scalars, enums, nulls and arrays, got "stdClass".');

        new PurgeContext(['nested' => ['value' => new \stdClass()]]);
    }

    #[TestWith([\INF])]
    #[TestWith([-\INF])]
    #[TestWith([\NAN])]
    public function testContextWithNonFiniteFloatIsRejected(float $value): void
    {
        $this->expectException(\ValueError::class);
        $this->expectExceptionMessage('The context may not contain INF or NAN.');

        new PurgeContext(['nested' => ['limit' => $value]]);
    }
}
