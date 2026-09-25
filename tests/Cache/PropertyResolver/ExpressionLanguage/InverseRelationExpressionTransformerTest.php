<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Cache\PropertyResolver\ExpressionLanguage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Cache\PropertyResolver\ExpressionLanguage\InverseRelationExpressionTransformer;
use Sofascore\PurgatoryBundle\Exception\AccessorNotInferableException;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\PropertyInfo\PropertyReadInfo;
use Symfony\Component\PropertyInfo\PropertyReadInfoExtractorInterface;

#[CoversClass(InverseRelationExpressionTransformer::class)]
final class InverseRelationExpressionTransformerTest extends TestCase
{
    #[TestWith(['obj.objectType === "object"', 'obj.foo.objectType === "object"'], 'name containing obj')]
    #[TestWith(['is_object(obj.obj)', 'is_object(obj.foo.obj)'], 'function containing obj')]
    #[TestWith(['obj.obj === "x"', 'obj.foo.obj === "x"'], 'property named obj')]
    #[TestWith(['obj(obj)', 'obj(obj.foo)'], 'function named obj')]
    #[TestWith(['obj (obj) and obj.a', 'obj (obj.foo) and obj.foo.a'], 'function named obj with a space')]
    #[TestWith(['obj?.bar', 'obj.foo?.bar'], 'null-safe')]
    #[TestWith(['obj["key"] > 1', 'obj.foo["key"] > 1'], 'array access')]
    #[TestWith(['not obj.active and !obj.hidden', 'not obj.foo.active and !obj.foo.hidden'], 'negation')]
    #[TestWith(['(obj.a ?? obj.b) > 0', '(obj.foo.a ?? obj.foo.b) > 0'], 'null coalescing')]
    #[TestWith(['(obj.a ?: obj.b) > 0', '(obj.foo.a ?: obj.foo.b) > 0'], 'elvis')]
    #[TestWith(['[obj.a, obj] != []', '[obj.foo.a, obj.foo] != []'], 'array')]
    #[TestWith(['obj.map[obj.key] > 0', 'obj.foo.map[obj.foo.key] > 0'], 'array index')]
    #[TestWith(['[obj.a ? obj : obj.b] != []', '[obj.foo.a ? obj.foo : obj.foo.b] != []'], 'ternary in array')]
    #[TestWith(['{a: obj.a}["a"] > 0', '{a: obj.foo.a}["a"] > 0'], 'hash value')]
    #[TestWith(['{obj: obj.a, b: obj}["obj"] > 0', '{obj: obj.foo.a, b: obj.foo}["obj"] > 0'], 'hash key')]
    #[TestWith(['{ obj : 1 }["obj"] == obj.a', '{ obj : 1 }["obj"] == obj.foo.a'], 'hash key with spaces')]
    #[TestWith(['{"obj": obj.a}["obj"] > 0', '{"obj": obj.foo.a}["obj"] > 0'], 'quoted hash key')]
    #[TestWith(['obj.name == "the obj"', 'obj.foo.name == "the obj"'], 'double-quoted string')]
    #[TestWith(['obj.name == \'the obj\'', 'obj.foo.name == \'the obj\''], 'single-quoted string')]
    #[TestWith(['obj.name == "a \\" obj"', 'obj.foo.name == "a \\" obj"'], 'escaped quote in string')]
    #[TestWith(['obj.type in ["obj", "object"]', 'obj.foo.type in ["obj", "object"]'], 'array of strings')]
    #[TestWith(['obj.path matches "/obj/"', 'obj.foo.path matches "/obj/"'], 'regex')]
    public function testTransform(string $expression, string $expected): void
    {
        $extractor = self::createStub(PropertyReadInfoExtractorInterface::class);
        $extractor->method('getReadInfo')->willReturn(
            new PropertyReadInfo(
                type: PropertyReadInfo::TYPE_PROPERTY,
                name: 'foo',
                visibility: PropertyReadInfo::VISIBILITY_PUBLIC,
                static: false,
                byRef: false,
            ),
        );

        $expressionTransformer = new InverseRelationExpressionTransformer($extractor);

        self::assertSame(
            "obj.foo !== null ? ($expected) : false",
            (string) $expressionTransformer->transform(new Expression($expression), \stdClass::class, 'prop', 'false'),
        );
    }

    #[TestWith(['null', PropertyReadInfo::TYPE_PROPERTY, 'foo', 'obj.foo !== null ? (obj.foo.firstName~"-"~obj.foo.lastName) : null'])]
    #[TestWith(['false', PropertyReadInfo::TYPE_METHOD, 'isBar', 'obj.isBar() !== null ? (obj.isBar().firstName~"-"~obj.isBar().lastName) : false'])]
    public function testTransformWithAccessorAndFallback(string $fallback, string $readInfoType, string $readInfoName, string $expectedExpression): void
    {
        $extractor = $this->createMock(PropertyReadInfoExtractorInterface::class);
        $extractor->expects(self::once())
            ->method('getReadInfo')
            ->with(\stdClass::class, 'prop')
            ->willReturn(
                new PropertyReadInfo(
                    type: $readInfoType,
                    name: $readInfoName,
                    visibility: PropertyReadInfo::VISIBILITY_PUBLIC,
                    static: false,
                    byRef: false,
                ),
            );

        $expressionTransformer = new InverseRelationExpressionTransformer($extractor);

        $expression = $expressionTransformer->transform(new Expression('obj.firstName~"-"~obj.lastName'), \stdClass::class, 'prop', $fallback);

        self::assertSame($expectedExpression, (string) $expression);
    }

    public function testExceptionIsThrownWhenPropertyIsNotAccessible(): void
    {
        $extractor = $this->createMock(PropertyReadInfoExtractorInterface::class);
        $extractor->expects(self::once())
            ->method('getReadInfo')
            ->with(\stdClass::class, 'prop')
            ->willReturn(null);

        $expressionTransformer = new InverseRelationExpressionTransformer($extractor);

        $this->expectException(AccessorNotInferableException::class);

        $expressionTransformer->transform(new Expression('obj.firstName~"-"~obj.lastName'), \stdClass::class, 'prop', 'null');
    }
}
