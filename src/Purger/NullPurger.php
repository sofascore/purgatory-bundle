<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Purger;

final class NullPurger implements PurgerInterface
{
    public function purge(iterable $urls): void
    {
    }
}
