<?php

namespace Sofascore\PurgatoryBundle\Purger;

interface PurgerInterface
{
    public function purge(iterable $urls): void;
}
