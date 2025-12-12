<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Attribute\RouteParamValue;

use Sofascore\PurgatoryBundle\Exception\LogicException;
use Symfony\Component\ExpressionLanguage\Expression;

final class ExpressionValues extends AbstractValues
{
    private readonly Expression $expression;

    public function __construct(
        string|Expression $expression,
    ) {
        $this->expression = self::normalizeExpression($expression);
    }

    /**
     * @return list<Expression>
     */
    public function getValues(): array
    {
        return [$this->expression];
    }

    public function toArray(): array
    {
        return [
            'type' => self::type(),
            'values' => [(string) $this->expression],
        ];
    }

    public static function type(): string
    {
        return 'expression';
    }

    private static function normalizeExpression(string|Expression $expression): Expression
    {
        if ($expression instanceof Expression) {
            return $expression;
        }

        if (!class_exists(Expression::class)) {
            throw new LogicException(\sprintf('You cannot use "%s" because the Symfony ExpressionLanguage component is not installed. Try running "composer require symfony/expression-language".', self::class));
        }

        return new Expression($expression);
    }
}
