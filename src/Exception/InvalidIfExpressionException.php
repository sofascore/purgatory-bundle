<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Exception;

use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\SyntaxError;

final class InvalidIfExpressionException extends RuntimeException
{
    private const MESSAGE = 'Invalid "if" expression: "%s"';

    public function __construct(Expression $expression, SyntaxError $syntaxError)
    {
        parent::__construct(
            message: \sprintf(self::MESSAGE, $expression),
            previous: $syntaxError,
        );
    }
}
