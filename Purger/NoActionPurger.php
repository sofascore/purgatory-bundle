<?php

declare(strict_types=1);

namespace SofaScore\Purgatory\Purger;


final class NoActionPurger implements PurgerInterface
{
    public function purge(iterable $urls): void
    {
        //Do nothing
    }
}
