<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Exception;

final class PropertyNotAccessibleException extends RuntimeException
{
    private const MESSAGE = 'Unable to create a getter for property "%s::%s".';

    public function __construct(
        public readonly string $class,
        public readonly string $property,
    ) {
        parent::__construct(
            message: sprintf(self::MESSAGE, $class, $property),
        );
    }
}
