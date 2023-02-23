<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Mapping\CacheWarmer;

use Sofascore\PurgatoryBundle\Mapping\Loader\AnnotationsLoader;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

class AnnotationsLoaderWarmer implements CacheWarmerInterface
{
    protected AnnotationsLoader $loader;

    public function __construct(AnnotationsLoader $loader)
    {
        $this->loader = $loader;
    }

    /**
     * Warms up the cache.
     *
     * @param string $cacheDir The cache directory
     *
     * @return string[]
     */
    public function warmUp(string $cacheDir): array
    {
        return $this->loader->warmUp($cacheDir);
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
    public function isOptional(): bool
    {
        return true;
    }
}
