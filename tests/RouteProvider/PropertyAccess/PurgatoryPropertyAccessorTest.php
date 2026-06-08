<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\RouteProvider\PropertyAccess;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Exception\ValueNotIterableException;
use Sofascore\PurgatoryBundle\RouteProvider\PropertyAccess\PurgatoryPropertyAccessor;
use Sofascore\PurgatoryBundle\Tests\RouteProvider\PropertyAccess\Fixtures\Foo;
use Symfony\Component\PropertyAccess\PropertyAccess;

#[CoversClass(PurgatoryPropertyAccessor::class)]
final class PurgatoryPropertyAccessorTest extends TestCase
{
    private PurgatoryPropertyAccessor $purgatoryPropertyAccessor;

    protected function setUp(): void
    {
        $this->purgatoryPropertyAccessor = new PurgatoryPropertyAccessor(
            propertyAccessor: PropertyAccess::createPropertyAccessor(),
        );
    }

    protected function tearDown(): void
    {
        unset($this->purgatoryPropertyAccessor);
        parent::tearDown();
    }

    #[DataProvider('traversableProvider')]
    public function testReadPath(object $object, string $propertyPath, array $expectedResult): void
    {
        self::assertTrue($this->purgatoryPropertyAccessor->isReadable($object, $propertyPath));
        self::assertSame(
            expected: $expectedResult,
            actual: $this->purgatoryPropertyAccessor->getValue($object, $propertyPath),
        );
    }

    public static function traversableProvider(): iterable
    {
        yield 'collection of nested entities from depth 1' => [
            'object' => new Foo(
                id: 100,
                children: new ArrayCollection([
                    new Foo(id: 1, children: new ArrayCollection([])),
                    new Foo(id: 2, children: new ArrayCollection([])),
                    new Foo(id: 3, children: new ArrayCollection([])),
                ]),
            ),
            'propertyPath' => 'children[*].id',
            'expectedResult' => [1, 2, 3],
        ];

        yield 'array of nested entities from depth 1' => [
            'object' => new Foo(
                id: 100,
                children: new ArrayCollection([
                    new Foo(id: 1, children: new ArrayCollection([])),
                    new Foo(id: 2, children: new ArrayCollection([])),
                    new Foo(id: 3, children: new ArrayCollection([])),
                ]),
            ),
            'propertyPath' => 'childrenArray[*].id',
            'expectedResult' => [1, 2, 3],
        ];

        yield 'collection of nested entities from depth 2' => [
            'object' => new Foo(
                id: 100,
                children: new ArrayCollection([
                    new Foo(
                        id: 1,
                        children: new ArrayCollection([
                            new Foo(id: 1000, children: new ArrayCollection([])),
                            new Foo(id: 1001, children: new ArrayCollection([])),
                            new Foo(id: 1002, children: new ArrayCollection([])),
                        ]),
                    ),
                    new Foo(
                        id: 2,
                        children: new ArrayCollection([]),
                    ),
                    new Foo(
                        id: 1,
                        children: new ArrayCollection([
                            new Foo(id: 1003, children: new ArrayCollection([])),
                            new Foo(id: 1004, children: new ArrayCollection([])),
                            new Foo(id: 1005, children: new ArrayCollection([])),
                        ]),
                    ),
                ]),
            ),
            'propertyPath' => 'children[*].children[*].id',
            'expectedResult' => [1000, 1001, 1002, 1003, 1004, 1005],
        ];

        yield 'array of nested entities from depth 2' => [
            'object' => new Foo(
                id: 100,
                children: new ArrayCollection([
                    new Foo(
                        id: 1,
                        children: new ArrayCollection([
                            new Foo(id: 1000, children: new ArrayCollection([])),
                            new Foo(id: 1001, children: new ArrayCollection([])),
                            new Foo(id: 1002, children: new ArrayCollection([])),
                        ]),
                    ),
                    new Foo(
                        id: 2,
                        children: new ArrayCollection([]),
                    ),
                    new Foo(
                        id: 1,
                        children: new ArrayCollection([
                            new Foo(id: 1003, children: new ArrayCollection([])),
                            new Foo(id: 1004, children: new ArrayCollection([])),
                            new Foo(id: 1005, children: new ArrayCollection([])),
                        ]),
                    ),
                ]),
            ),
            'propertyPath' => 'children[*].childrenArray[*].id',
            'expectedResult' => [1000, 1001, 1002, 1003, 1004, 1005],
        ];
    }

    #[DataProvider('nullSafeProvider')]
    public function testNullSafeCollectionPath(object $object, string $propertyPath, mixed $expectedResult): void
    {
        self::assertTrue($this->purgatoryPropertyAccessor->isReadable($object, $propertyPath));
        self::assertSame(
            expected: $expectedResult,
            actual: $this->purgatoryPropertyAccessor->getValue($object, $propertyPath),
        );
    }

    public static function nullSafeProvider(): iterable
    {
        yield 'null-safe parent short-circuits to null' => [
            'object' => new Foo(
                id: 100,
                children: new ArrayCollection([]),
                linked: null,
            ),
            'propertyPath' => 'linked?.children[*].id',
            'expectedResult' => null,
        ];

        yield 'null-safe parent present with populated collection yields values' => [
            'object' => new Foo(
                id: 100,
                children: new ArrayCollection([]),
                linked: new Foo(
                    id: 1,
                    children: new ArrayCollection([
                        new Foo(id: 10, children: new ArrayCollection([])),
                        new Foo(id: 11, children: new ArrayCollection([])),
                    ]),
                ),
            ),
            'propertyPath' => 'linked?.children[*].id',
            'expectedResult' => [10, 11],
        ];

        yield 'null-safe collection element resolving to null short-circuits to null' => [
            'object' => new Foo(
                id: 100,
                children: new ArrayCollection([]),
                nullableChildren: null,
            ),
            'propertyPath' => 'nullableChildren?[*].id',
            'expectedResult' => null,
        ];

        yield 'nested null-safe short-circuit contributes a null entry, consistent with a scalar leaf' => [
            'object' => new Foo(
                id: 0,
                children: new ArrayCollection([
                    new Foo(id: 1, children: new ArrayCollection([]), linked: null),
                    new Foo(
                        id: 2,
                        children: new ArrayCollection([]),
                        linked: new Foo(
                            id: 20,
                            children: new ArrayCollection([
                                new Foo(id: 100, children: new ArrayCollection([])),
                                new Foo(id: 101, children: new ArrayCollection([])),
                            ]),
                        ),
                    ),
                ]),
            ),
            'propertyPath' => 'children[*].linked?.children[*].id',
            'expectedResult' => [null, 100, 101],
        ];
    }

    #[DataProvider('notTraversableProvider')]
    public function testNotTraversableThrows(object $object, string $propertyPath, string $expectedMessage): void
    {
        self::assertFalse($this->purgatoryPropertyAccessor->isReadable($object, $propertyPath));

        $this->expectException(ValueNotIterableException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->purgatoryPropertyAccessor->getValue($object, $propertyPath);
    }

    public static function notTraversableProvider(): iterable
    {
        yield 'scalar value is not iterable' => [
            'object' => new Foo(
                id: 1,
                children: new ArrayCollection([]),
            ),
            'propertyPath' => 'id[*].id',
            'expectedMessage' => 'Expected an iterable, "int" given at property path "id[*]".',
        ];

        yield 'non-null-safe collection resolving to null' => [
            'object' => new Foo(
                id: 100,
                children: new ArrayCollection([]),
                nullableChildren: null,
            ),
            'propertyPath' => 'nullableChildren[*].id',
            'expectedMessage' => 'Expected an iterable, "null" given at property path "nullableChildren[*]".',
        ];

        yield 'null-safe parent present but non-null-safe collection resolving to null' => [
            'object' => new Foo(
                id: 100,
                children: new ArrayCollection([]),
                linked: new Foo(
                    id: 1,
                    children: new ArrayCollection([]),
                    nullableChildren: null,
                ),
            ),
            'propertyPath' => 'linked?.nullableChildren[*].id',
            'expectedMessage' => 'Expected an iterable, "null" given at property path "linked?.nullableChildren[*]".',
        ];
    }
}
