<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\RouteParamValueResolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle2\RouteParamValueResolver\DynamicValuesResolver;
use Sofascore\PurgatoryBundle2\Tests\RouteParamValueResolver\Fixtures\DummyServiceOne;
use Sofascore\PurgatoryBundle2\Tests\RouteParamValueResolver\Fixtures\DummyServiceTwo;
use Sofascore\PurgatoryBundle2\Tests\RouteParamValueResolver\Fixtures\Foo;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\PropertyAccess\PropertyAccess;

#[CoversClass(DynamicValuesResolver::class)]
final class DynamicValuesResolverTest extends TestCase
{
    private DynamicValuesResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new DynamicValuesResolver(
            paramResolverServiceLocator: new ServiceLocator(
                factories: [
                    'service_one' => static fn () => new DummyServiceOne(),
                    'service_two' => static fn () => new DummyServiceTwo(),
                ],
            ),
            propertyAccessor: PropertyAccess::createPropertyAccessor(),
        );
    }

    protected function tearDown(): void
    {
        unset($this->resolver);
        parent::tearDown();
    }

    #[DataProvider('valuesProvider')]
    public function testResolve(
        string $id,
        ?string $method,
        ?string $path,
        object $entity,
        array $expectedResult,
    ): void {
        self::assertSame(
            expected: $expectedResult,
            actual: $this->resolver->resolve(
                unresolvedValues: [$id, $method, $path],
                entity: $entity,
            ),
        );
    }

    public static function valuesProvider(): iterable
    {
        $foo = new Foo();
        $foo->id = 5;

        yield 'invokable service' => [
            'id' => 'service_one',
            'method' => null,
            'path' => null,
            'entity' => $foo,
            'expectedResult' => [1005],
        ];

        $foo = clone $foo;
        yield 'service with method' => [
            'id' => 'service_two',
            'method' => 'getValueToPurge',
            'path' => null,
            'entity' => $foo,
            'expectedResult' => [105],
        ];

        $foo = clone $foo;
        $child = new Foo();
        $child->id = 12;
        $foo->child = $child;

        yield 'with path to arg' => [
            'id' => 'service_two',
            'method' => 'getValueToPurge',
            'path' => 'child',
            'entity' => $foo,
            'expectedResult' => [112],
        ];

        $foo = clone $foo;
        yield 'with array unpacking' => [
            'id' => 'service_two',
            'method' => 'getArrayValueToPurge',
            'path' => null,
            'entity' => $foo,
            'expectedResult' => [5, 1, 2, 3],
        ];
    }
}
