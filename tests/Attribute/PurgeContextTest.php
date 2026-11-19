<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Attribute\PurgeContext;

#[CoversClass(PurgeContext::class)]
final class PurgeContextTest extends TestCase
{
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
