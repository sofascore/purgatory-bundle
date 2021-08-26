<?php


namespace SofaScore\Purgatory\Purger;


class DefaultPurger implements PurgerInterface
{

    public function purge(iterable $urls): void
    {
        throw new \Exception('Not implemented');
    }
}
