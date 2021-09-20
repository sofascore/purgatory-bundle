<?php

declare(strict_types=1);

namespace SofaScore\Purgatory\Purger;


final class NullPurger implements PurgerInterface
{
    public function purge(iterable $urls): void
    {
        //Do nothing
    }
}
