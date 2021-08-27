<?php

namespace SofaScore\Purgatory\Purger;

use Symfony\Component\HttpKernel\HttpCache\StoreInterface;

class SymfonyPurger implements PurgerInterface
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
            $this->store->purge($this->host . $url);
        }
    }
}

