<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Cache\RouteMetadata;

use Sofascore\PurgatoryBundle\Attribute\PurgeOn;
use Symfony\Component\Routing\Route;

final class RouteMetadata
{
    /**
     * The context the purge subscription passes to the purger.
     *
     * @var array<array-key, mixed>
     */
    public readonly array $context;

    /**
     * @param ?array<array-key, mixed> $context defaults to the context of the given PurgeOn
     */
    public function __construct(
        public readonly string $routeName,
        public readonly Route $route,
        public readonly PurgeOn $purgeOn,
        public readonly ?\ReflectionMethod $reflectionMethod,
        ?array $context = null,
    ) {
        $this->context = $context ?? $purgeOn->context;
    }
}
