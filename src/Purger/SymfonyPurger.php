<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Purger;

use Symfony\Component\HttpKernel\HttpCache\StoreInterface;

final class SymfonyPurger implements PurgerInterface
{
    public function __construct(
        private readonly StoreInterface $store,
        private readonly string $host,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function purge(iterable $urls): void
    {
        foreach ($urls as $url) {
            $this->store->purge($this->host.$url);
        }
    }
}
