<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Cache\Metadata;

interface PurgeSubscriptionProviderInterface
{
    /**
     * @return iterable<PurgeSubscription>
     */
    public function provide(): iterable;
}
