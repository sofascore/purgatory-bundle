<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Attribute\RouteParamValue;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\ExpressionValues;
use Symfony\Component\ExpressionLanguage\Expression;

#[CoversClass(ExpressionValues::class)]
final class ExpressionValuesTest extends TestCase
{
    #[TestWith(['obj.getHeight() * obj.getWidth()'])]
    #[TestWith([new Expression('obj.getHeight() * obj.getWidth()')])]
    public function testValueNormalization(string|Expression $expression): void
    {
        $value = (new ExpressionValues($expression))->expression;

        self::assertSame((string) $expression, (string) $value);
    }
}
