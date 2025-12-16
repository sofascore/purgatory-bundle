<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Cache\PropertyResolver\InverseValuesBuilder;

use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\ExpressionValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\ValuesInterface;
use Sofascore\PurgatoryBundle\Cache\PropertyResolver\ExpressionLanguage\InverseRelationExpressionTransformer;

/**
 * @implements InverseValuesBuilderInterface<ExpressionValues>
 */
final class ExpressionInverseValuesBuilder implements InverseValuesBuilderInterface
{
    public function __construct(
        private readonly InverseRelationExpressionTransformer $expressionTransformer,
    ) {
    }

    public static function for(): string
    {
        return ExpressionValues::type();
    }

    public function build(ValuesInterface $values, string $associationClass, string $associationTarget): ValuesInterface
    {
        $expression = $values->expression;

        $inverseExpression = $this->expressionTransformer->transform($expression, $associationClass, $associationTarget, 'null');

        return new ExpressionValues($inverseExpression);
    }
}
