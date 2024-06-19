<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\RouteParamValueResolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle2\RouteParamValueResolver\PropertyValuesResolver;
use Symfony\Component\PropertyAccess\PropertyAccess;

#[CoversClass(PropertyValuesResolver::class)]
final class PropertyValuesResolverTest extends TestCase
{
    public function testResolve(): void
    {
        $resolver = new PropertyValuesResolver(PropertyAccess::createPropertyAccessor());

        $entity = new \stdClass();
        $entity->prop1 = 'foo';
        $entity->prop2 = 'bar';
        $entity->prop3 = 'baz';

        self::assertSame(['foo', 'bar', 'baz'], $resolver->resolve(['prop1', 'prop2', 'prop3'], $entity));
    }
}
