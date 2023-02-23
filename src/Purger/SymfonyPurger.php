<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Purger;

use Symfony\Component\HttpKernel\HttpCache\StoreInterface;

final class SymfonyPurger implements PurgerInterface
{
    private StoreInterface $store;
    private string $host;

    public function __construct(StoreInterface $store, string $host)
    {
        $this->store = $store;
        $this->host = $host;
    }

    public function purge(iterable $urls): void
    {
        foreach ($urls as $url) {
            $this->store->purge($this->host.$url);
        }
    }
}
