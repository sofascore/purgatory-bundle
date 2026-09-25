<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Exception;

final class InvalidDynamicValuesClosureException extends InvalidArgumentException
{
    private const MESSAGE = 'Invalid "DynamicValues" closure provided for route "%s": "%s"';

    public function __construct(
        public readonly string $routeName,
        string $reason,
    ) {
        parent::__construct(\sprintf(self::MESSAGE, $routeName, $reason));
    }
}
