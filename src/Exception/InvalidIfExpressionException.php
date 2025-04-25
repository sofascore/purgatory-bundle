<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Exception;

use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\SyntaxError;

final class InvalidIfExpressionException extends InvalidArgumentException
{
    private const MESSAGE = 'Invalid "if" expression provided: "%s"';

    public function __construct(
        public readonly Expression $expression,
        SyntaxError $syntaxError,
    ) {
        parent::__construct(
            message: \sprintf(self::MESSAGE, $syntaxError->getMessage()),
            previous: $syntaxError,
        );
    }
}
