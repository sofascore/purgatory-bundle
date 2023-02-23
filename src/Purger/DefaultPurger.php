<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Purger;

final class DefaultPurger implements PurgerInterface
{
    public function purge(iterable $urls): void
    {
        throw new \BadMethodCallException('Not implemented');
    }
}
