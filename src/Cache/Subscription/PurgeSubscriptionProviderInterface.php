<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Cache\Subscription;

interface PurgeSubscriptionProviderInterface
{
    /**
     * @return iterable<PurgeSubscription>
     */
    public function provide(): iterable;
}
