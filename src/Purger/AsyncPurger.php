<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Purger;

use Sofascore\PurgatoryBundle2\Purger\Messenger\PurgeMessage;
use Symfony\Component\Messenger\MessageBusInterface;

final class AsyncPurger implements PurgerInterface
{
    /**
     * @param ?positive-int $batchSize
     */
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly ?int $batchSize = null,
    ) {
    }

    public function purge(iterable $urls): void
    {
        /** @var list<string> $urls */
        $urls = \is_array($urls) ? $urls : iterator_to_array($urls);

        if (null !== $this->batchSize) {
            foreach (array_chunk($urls, $this->batchSize) as $batch) {
                $this->bus->dispatch(new PurgeMessage($batch));
            }
        } else {
            $this->bus->dispatch(new PurgeMessage($urls));
        }
    }
}
