<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Purger;

use Sofascore\PurgatoryBundle\Purger\Messenger\PurgeMessage;
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

    public function purge(iterable $purgeRequests): void
    {
        if (!$purgeRequests) {
            return;
        }

        /** @var list<PurgeRequest> $purgeRequests */
        $purgeRequests = \is_array($purgeRequests) ? $purgeRequests : iterator_to_array($purgeRequests, false);

        if (null !== $this->batchSize) {
            foreach (array_chunk($purgeRequests, $this->batchSize) as $batch) {
                $this->bus->dispatch(new PurgeMessage($batch));
            }
        } else {
            $this->bus->dispatch(new PurgeMessage($purgeRequests));
        }
    }
}
