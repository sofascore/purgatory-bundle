<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Exception;

final class InvalidIfCallableException extends InvalidArgumentException
{
    private const MESSAGE = 'Invalid "if" callable provided for route "%s": "%s"';

    public function __construct(
        public readonly string $routeName,
        string $reason,
    ) {
        parent::__construct(\sprintf(self::MESSAGE, $routeName, $reason));
    }
}
