<?php

namespace SofaScore\Purgatory\Purger;

interface PurgerInterface
{
    public function purge(iterable $urls): void;
}
