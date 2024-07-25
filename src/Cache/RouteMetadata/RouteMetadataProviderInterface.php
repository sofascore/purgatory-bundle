<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Cache\RouteMetadata;

interface RouteMetadataProviderInterface
{
    /**
     * @return iterable<RouteMetadata>
     */
    public function provide(): iterable;
}
