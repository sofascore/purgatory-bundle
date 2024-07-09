<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Exception;

final class RouteNotFoundException extends RuntimeException
{
    private const MESSAGE = 'The route "%s" does not exist.';

    public function __construct(
        public readonly string $routeName,
    ) {
        parent::__construct(
            message: sprintf(self::MESSAGE, $routeName),
        );
    }
}
