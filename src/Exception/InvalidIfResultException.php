<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Exception;

final class InvalidIfResultException extends RuntimeException
{
    private const MESSAGE = 'Expected return value of "if" expression (%s) to be boolean, got %s';

    public function __construct(
        public readonly string $expression,
        public readonly mixed $result,
    ) {
        parent::__construct(\sprintf(self::MESSAGE, $expression, \gettype($result)));
    }
}
