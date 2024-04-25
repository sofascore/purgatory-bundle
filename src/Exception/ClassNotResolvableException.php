<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Exception;

final class ClassNotResolvableException extends \LogicException implements PurgatoryException
{
    private const MESSAGE = 'Could not resolve "%s"';

    public function __construct(
        string $serviceIdOrClass,
    ) {
        parent::__construct(
            message: sprintf(self::MESSAGE, $serviceIdOrClass),
        );
    }
}
