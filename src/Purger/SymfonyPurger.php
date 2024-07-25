<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Purger;

use Symfony\Component\HttpKernel\HttpCache\StoreInterface;

final class SymfonyPurger implements PurgerInterface
{
    public function __construct(
        private readonly StoreInterface $store,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function purge(iterable $urls): void
    {
        foreach ($urls as $url) {
            $this->store->purge($url);
        }
    }
}
