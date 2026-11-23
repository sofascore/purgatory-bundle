<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Cache\RouteMetadata\Fixtures;

use Sofascore\PurgatoryBundle\Attribute\PurgeContext;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final class PurgePriority extends PurgeContext
{
    public function __construct(string $priority)
    {
        parent::__construct(['priority' => $priority]);
    }
}
