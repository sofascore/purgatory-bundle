<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Cache\ControllerMetadata;

interface ControllerMetadataProviderInterface
{
    /**
     * @return iterable<ControllerMetadata>
     */
    public function provide(): iterable;
}
