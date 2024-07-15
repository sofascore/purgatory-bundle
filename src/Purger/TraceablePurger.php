<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Purger;

use Sofascore\PurgatoryBundle2\DataCollector\PurgatoryDataCollector;

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

    public function purge(iterable $urls): void
    {
        /** @var list<string> $urls */
        $urls = \is_array($urls) ? $urls : iterator_to_array($urls);

        $startTime = microtime(true);
        $this->purger->purge($urls);
        $time = microtime(true) - $startTime;

        $this->dataCollector->collectPurgedUrls($urls, $time);
    }
}
