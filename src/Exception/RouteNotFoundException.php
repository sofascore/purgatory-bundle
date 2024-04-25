<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Exception;

final class RouteNotFoundException extends \RuntimeException implements PurgatoryException
{
    private const MESSAGE = "Route '%s' not found.";

    public function __construct(
        string $routeName,
    ) {
        parent::__construct(
            message: sprintf(self::MESSAGE, $routeName),
        );
    }
}
