<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Exception;

final class InvalidIfExpressionResultException extends \TypeError implements PurgatoryException
{
    private const MESSAGE = 'Expected the return value of "if" expression (%s) for route "%s" to be boolean, got %s.';

    public function __construct(
        public readonly string $routeName,
        public readonly string $expression,
        public readonly mixed $result,
    ) {
        parent::__construct(\sprintf(self::MESSAGE, $expression, $routeName, get_debug_type($result)));
    }
}
