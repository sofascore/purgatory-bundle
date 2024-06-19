<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\RouteParamValueResolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle2\RouteParamValueResolver\RawValuesResolver;

#[CoversClass(RawValuesResolver::class)]
final class RawValuesResolverTest extends TestCase
{
    public function testResolve(): void
    {
        $resolver = new RawValuesResolver();

        self::assertSame(['foo', 'bar', 'baz'], $resolver->resolve(['foo', 'bar', 'baz'], new \stdClass()));
    }
}
