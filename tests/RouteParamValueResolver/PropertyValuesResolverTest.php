<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\RouteParamValueResolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle2\RouteParamValueResolver\PropertyValuesResolver;
use Sofascore\PurgatoryBundle2\RouteProvider\PropertyAccess\PurgatoryPropertyAccessor;
use Symfony\Component\PropertyAccess\PropertyAccess;

#[CoversClass(PropertyValuesResolver::class)]
final class PropertyValuesResolverTest extends TestCase
{
    public function testResolve(): void
    {
        $resolver = new PropertyValuesResolver(
            new PurgatoryPropertyAccessor(PropertyAccess::createPropertyAccessor()),
        );

        $entity = new \stdClass();
        $entity->prop1 = 'foo';
        $entity->prop2 = 'bar';
        $entity->prop3 = 'baz';
        $entity->prop4 = ['qux', 'corge'];

        self::assertSame(['foo', 'bar', 'baz', 'qux', 'corge'], $resolver->resolve(['prop1', 'prop2', 'prop3', 'prop4'], $entity));
    }
}
