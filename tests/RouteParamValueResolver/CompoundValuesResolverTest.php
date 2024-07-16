<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\RouteParamValueResolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\EnumValues;
use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\RawValues;
use Sofascore\PurgatoryBundle2\RouteParamValueResolver\CompoundValuesResolver;
use Sofascore\PurgatoryBundle2\RouteParamValueResolver\EnumValuesResolver;
use Sofascore\PurgatoryBundle2\RouteParamValueResolver\RawValuesResolver;
use Sofascore\PurgatoryBundle2\Tests\Fixtures\DummyIntEnum;
use Symfony\Component\DependencyInjection\ServiceLocator;

#[CoversClass(CompoundValuesResolver::class)]
final class CompoundValuesResolverTest extends TestCase
{
    public function testResolve(): void
    {
        $resolver = new CompoundValuesResolver(new ServiceLocator([
            EnumValues::type() => static fn () => new EnumValuesResolver(),
            RawValues::type() => static fn () => new RawValuesResolver(),
        ]));

        self::assertSame(['foo', 'bar', 1, 2, 3], $resolver->resolve([
            ['type' => RawValues::type(), 'values' => ['foo', 'bar']],
            ['type' => EnumValues::type(), 'values' => [DummyIntEnum::class]],
        ], new \stdClass()));
    }
}
