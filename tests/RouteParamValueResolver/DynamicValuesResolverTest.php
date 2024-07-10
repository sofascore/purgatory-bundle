<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\RouteParamValueResolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle2\RouteParamValueResolver\DynamicValuesResolver;
use Sofascore\PurgatoryBundle2\RouteProvider\PropertyAccess\PurgatoryPropertyAccessor;
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
            routeParamServiceLocator: new ServiceLocator(
                factories: [
                    'service_one' => static fn () => (new DummyServiceOne())->__invoke(...),
                    'service_two' => static fn () => (new DummyServiceTwo())->getValueToPurge(...),
                    'service_three' => static fn () => (new DummyServiceTwo())->getArrayValueToPurge(...),
                ],
            ),
            propertyAccessor: new PurgatoryPropertyAccessor(PropertyAccess::createPropertyAccessor()),
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
        ?string $path,
        object $entity,
        array $expectedResult,
    ): void {
        self::assertSame(
            expected: $expectedResult,
            actual: $this->resolver->resolve(
                unresolvedValues: [$id, $path],
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
            'path' => null,
            'entity' => $foo,
            'expectedResult' => [1005],
        ];

        $foo = clone $foo;
        yield 'service with method' => [
            'id' => 'service_two',
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
            'path' => 'child',
            'entity' => $foo,
            'expectedResult' => [112],
        ];

        $foo = clone $foo;
        yield 'with array unpacking' => [
            'id' => 'service_three',
            'path' => null,
            'entity' => $foo,
            'expectedResult' => [5, 1, 2, 3],
        ];
    }
}
