<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Purger;

use Sofascore\PurgatoryBundle\DataCollector\PurgatoryDataCollector;

/**
 * @internal
 */
final class TraceablePurger implements PurgerInterface
{
    public function __construct(
        private readonly PurgerInterface $purger,
        private readonly PurgatoryDataCollector $dataCollector,
    ) {
    }

    public function purge(iterable $purgeRequests): void
    {
        /** @var list<PurgeRequest> $purgeRequests */
        $purgeRequests = \is_array($purgeRequests) ? $purgeRequests : iterator_to_array($purgeRequests);

        $startTime = microtime(true);
        $this->purger->purge($purgeRequests);
        $time = microtime(true) - $startTime;

        $this->dataCollector->collectPurgeRequests($purgeRequests, $time);
    }
}
