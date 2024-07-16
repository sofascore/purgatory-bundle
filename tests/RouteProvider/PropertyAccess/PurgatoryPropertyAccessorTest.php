<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\RouteProvider\PropertyAccess;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle2\Exception\ValueNotIterableException;
use Sofascore\PurgatoryBundle2\RouteProvider\PropertyAccess\PurgatoryPropertyAccessor;
use Sofascore\PurgatoryBundle2\Tests\RouteProvider\PropertyAccess\Fixtures\Foo;
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

    public function testNotTraversable(): void
    {
        $this->expectException(ValueNotIterableException::class);
        $this->expectExceptionMessage('Expected an iterable, "int" given at property path "id[*]".');

        $this->purgatoryPropertyAccessor->getValue(
            objectOrArray: new Foo(
                id: 1,
                children: new ArrayCollection([]),
            ),
            propertyPath: 'id[*].id',
        );
    }
}
