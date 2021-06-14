<?php

namespace SofaScore\Purgatory\Mapping\CacheWarmer;

use SofaScore\Purgatory\Mapping\Loader\AnnotationsLoader;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;

class AnnotationsLoaderWarmer implements CacheWarmerInterface
{
    /**
     * @var AnnotationsLoader
     */
    protected $loader;

    public function __construct(AnnotationsLoader $loader)
    {
        $this->loader = $loader;
    }

    /**
     * Warms up the cache.
     *
     * @param string $cacheDir The cache directory
     */
    public function warmUp($cacheDir)
    {
        if ($this->loader instanceof WarmableInterface) {
            $this->loader->warmUp($cacheDir);
        }
    }

    /**
     * Checks whether this warmer is optional or not.
     *
     * Optional warmers can be ignored on certain conditions.
     *
     * A warmer should return true if the cache can be
     * generated incrementally and on-demand.
     *
     * @return bool true if the warmer is optional, false otherwise
     */
    public function isOptional()
    {
        return true;
    }
}
