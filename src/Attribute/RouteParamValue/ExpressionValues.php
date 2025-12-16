<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Attribute\RouteParamValue;

use Sofascore\PurgatoryBundle\Exception\LogicException;
use Symfony\Component\ExpressionLanguage\Expression;

final class ExpressionValues extends AbstractValues
{
    public readonly Expression $expression;

    public function __construct(
        string|Expression $expression,
    ) {
        $this->expression = self::normalizeExpression($expression);
    }

    /**
     * @return non-empty-list<string>
     */
    protected function getValues(): array
    {
        return [(string) $this->expression];
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
