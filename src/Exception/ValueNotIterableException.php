<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Exception;

final class ValueNotIterableException extends RuntimeException
{
    private const MESSAGE = 'Expected an iterable, "%s" given at property path "%s[*]".';

    public function __construct(
        mixed $value,
        string $propertyPath,
    ) {
        parent::__construct(
            message: sprintf(self::MESSAGE, get_debug_type($value), $propertyPath),
        );
    }
}
