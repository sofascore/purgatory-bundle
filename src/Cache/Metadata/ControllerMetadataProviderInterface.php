<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Cache\Metadata;

interface ControllerMetadataProviderInterface
{
    /**
     * @return iterable<ControllerMetadata>
     */
    public function provide(): iterable;
}
