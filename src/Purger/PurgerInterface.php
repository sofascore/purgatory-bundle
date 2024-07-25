<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Purger;

interface PurgerInterface
{
    /**
     * @param iterable<int, string> $urls
     */
    public function purge(iterable $urls): void;
}
