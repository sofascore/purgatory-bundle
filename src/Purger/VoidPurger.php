<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Purger;

final class VoidPurger implements PurgerInterface
{
    /**
     * {@inheritDoc}
     */
    public function purge(iterable $urls): void
    {
    }
}
