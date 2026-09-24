<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Exception;

final class PropertyNotAccessibleException extends RuntimeException
{
    private const MESSAGE = 'Unable to access property path "%s" on "%s".';

    public function __construct(
        public readonly string $class,
        public readonly string $property,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            message: \sprintf(self::MESSAGE, $property, $class),
            previous: $previous,
        );
    }
}
