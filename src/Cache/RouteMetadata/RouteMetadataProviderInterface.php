<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Cache\RouteMetadata;

interface RouteMetadataProviderInterface
{
    /**
     * @return iterable<RouteMetadata>
     */
    public function provide(): iterable;
}
