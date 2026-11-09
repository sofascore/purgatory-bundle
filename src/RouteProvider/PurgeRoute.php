<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\RouteProvider;

final class PurgeRoute
{
    /**
     * @param array<string, ?scalar>  $params
     * @param array<array-key, mixed> $context the `context` of the PurgeOn this route comes from
     */
    public function __construct(
        public readonly string $name,
        public readonly array $params,
        public readonly array $context = [],
    ) {
    }
}
