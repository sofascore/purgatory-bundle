<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Exception;

use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\SyntaxError;

final class InvalidIfExpressionException extends InvalidArgumentException
{
    private const MESSAGE = 'Invalid "if" expression provided for route "%s": "%s"';

    public function __construct(
        public readonly Expression $expression,
        public readonly string $route,
        SyntaxError $syntaxError,
    ) {
        parent::__construct(
            message: \sprintf(self::MESSAGE, $route, $syntaxError->getMessage()),
            previous: $syntaxError,
        );
    }
}
