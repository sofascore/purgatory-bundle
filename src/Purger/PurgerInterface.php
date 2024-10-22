<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Purger;

interface PurgerInterface
{
    /**
     * @param iterable<int, PurgeRequest> $purgeRequests
     */
    public function purge(iterable $purgeRequests): void;
}
