<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Purger;


final class NullPurger implements PurgerInterface
{
    public function purge(iterable $urls): void
    {
        //Do nothing
    }
}
