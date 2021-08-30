<?php


namespace SofaScore\Purgatory\Purger;


final class DefaultPurger implements PurgerInterface
{

    public function purge(iterable $urls): void
    {
        throw new \BadMethodCallException('Not implemented');
    }
}
