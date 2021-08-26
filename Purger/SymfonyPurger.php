<?php


namespace SofaScore\Purgatory\Purger;


use SofaScore\Purgatory\PurgatoryCacheKernel;

class SymfonyPurger implements PurgerInterface
{
    private PurgatoryCacheKernel $kernel;
    private string $host;

    public function __construct(PurgatoryCacheKernel $kernel, string $host)
    {
        $this->kernel = $kernel;
        $this->host = $host;
    }

    public function purge(iterable $urls): void
    {
        foreach ($urls as $url) {
            $this->kernel->invalidateUrl($this->host . $url);
        }
    }
}
