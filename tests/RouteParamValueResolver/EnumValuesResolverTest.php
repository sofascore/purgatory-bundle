<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\RouteParamValueResolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle2\RouteParamValueResolver\EnumValuesResolver;
use Sofascore\PurgatoryBundle2\Tests\Fixtures\DummyIntEnum;
use Sofascore\PurgatoryBundle2\Tests\Fixtures\DummyStringEnum;

#[CoversClass(EnumValuesResolver::class)]
final class EnumValuesResolverTest extends TestCase
{
    /**
     * @param class-string<\BackedEnum> $enumFqcn
     */
    #[TestWith([DummyStringEnum::class, ['case1', 'case2', 'case3']])]
    #[TestWith([DummyIntEnum::class, [1, 2, 3]])]
    public function testResolve(string $enumFqcn, array $expectedValues): void
    {
        $resolver = new EnumValuesResolver();

        self::assertSame($expectedValues, $resolver->resolve([$enumFqcn], new \stdClass()));
    }
}
