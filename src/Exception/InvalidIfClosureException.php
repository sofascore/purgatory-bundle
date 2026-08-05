<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Exception;

final class InvalidIfClosureException extends InvalidArgumentException
{
    public function __construct(
        public readonly string $routeName,
        string $message,
    ) {
        parent::__construct("Invalid 'if' closure for route '$routeName': $message");
    }
}
