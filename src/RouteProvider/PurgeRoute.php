<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\RouteProvider;

final class PurgeRoute
{
    /**
     * @param array<string, ?scalar> $params
     * @param array<string, ?scalar> $context
     */
    public function __construct(
        public readonly string $name,
        public readonly array $params,
        public readonly array $context = [],
    ) {
    }
}
