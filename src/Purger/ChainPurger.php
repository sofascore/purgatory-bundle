<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Purger;

final class ChainPurger implements PurgerInterface
{
    /**
     * @param iterable<PurgerInterface> $purgers
     */
    public function __construct(
        private readonly iterable $purgers,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function purge(iterable $purgeRequests): void
    {
        /** @var list<PurgeRequest> $purgeRequests */
        $purgeRequests = \is_array($purgeRequests) ? $purgeRequests : iterator_to_array($purgeRequests, false);

        foreach ($this->purgers as $purger) {
            $purger->purge($purgeRequests);
        }
    }
}
