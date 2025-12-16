<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Cache\PropertyResolver\ExpressionLanguage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Cache\PropertyResolver\ExpressionLanguage\InverseRelationExpressionTransformer;
use Sofascore\PurgatoryBundle\Exception\PropertyNotAccessibleException;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\PropertyInfo\PropertyReadInfo;
use Symfony\Component\PropertyInfo\PropertyReadInfoExtractorInterface;

#[CoversClass(InverseRelationExpressionTransformer::class)]
final class InverseRelationExpressionTransformerTest extends TestCase
{
    #[TestWith(['null', PropertyReadInfo::TYPE_PROPERTY, 'foo', 'obj.foo !== null ? (obj.foo.firstName~"-"~obj.foo.lastName) : null'])]
    #[TestWith(['false', PropertyReadInfo::TYPE_METHOD, 'isBar', 'obj.isBar() !== null ? (obj.isBar().firstName~"-"~obj.isBar().lastName) : false'])]
    public function testTransform(string $fallback, string $readInfoType, string $readInfoName, string $expectedExpression): void
    {
        $extractor = self::createStub(PropertyReadInfoExtractorInterface::class);
        $extractor->method('getReadInfo')
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
        $extractor = self::createStub(PropertyReadInfoExtractorInterface::class);
        $extractor->method('getReadInfo')
            ->with(\stdClass::class, 'prop')
            ->willReturn(null);

        $expressionTransformer = new InverseRelationExpressionTransformer($extractor);

        $this->expectException(PropertyNotAccessibleException::class);

        $expressionTransformer->transform(new Expression('obj.firstName~"-"~obj.lastName'), \stdClass::class, 'prop', 'null');
    }
}
