<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Exception;

final class ClassNotResolvableException extends \LogicException implements PurgatoryException
{
    private const MESSAGE = 'Unable to resolve class for "%s".';

    public function __construct(
        public readonly string $serviceIdOrClass,
    ) {
        parent::__construct(
            message: sprintf(self::MESSAGE, $serviceIdOrClass),
        );
    }
}
