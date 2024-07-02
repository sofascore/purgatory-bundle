<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Cache\RouteMetadata;

use Sofascore\PurgatoryBundle2\Attribute\PurgeOn;
use Symfony\Component\Routing\Route;

final class RouteMetadata
{
    public function __construct(
        public readonly string $routeName,
        public readonly Route $route,
        public readonly PurgeOn $purgeOn,
        public readonly \ReflectionMethod $reflectionMethod,
    ) {
    }
}
