<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\RouteProvider;

final class PurgeRoute
{
    /**
     * @param array<string, ?scalar> $params
     */
    public function __construct(
        public readonly string $name,
        public readonly array $params,
    ) {
    }
}
