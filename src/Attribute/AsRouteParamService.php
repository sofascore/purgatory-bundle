<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final class AsRouteParamService
{
    public function __construct(
        public readonly string $alias,
    ) {
    }
}
