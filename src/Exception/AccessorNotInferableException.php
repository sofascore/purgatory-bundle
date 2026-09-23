<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Exception;

final class AccessorNotInferableException extends RuntimeException
{
    private const MESSAGE = 'Unable to infer an accessor for property "%s::%s".';

    public function __construct(
        public readonly string $class,
        public readonly string $property,
    ) {
        parent::__construct(
            message: \sprintf(self::MESSAGE, $class, $property),
        );
    }
}
