<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Purger;

use Sofascore\PurgatoryBundle\RouteProvider\PurgeRoute;

final class PurgeRequest
{
    public function __construct(
        public readonly string $url,
        public readonly PurgeRoute $route,
    ) {
    }
}
