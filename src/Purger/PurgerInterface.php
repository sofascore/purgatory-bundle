<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Purger;

interface PurgerInterface
{
    public function purge(iterable $urls): void;
}
