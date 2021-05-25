<?php

namespace SofaScore\CacheRefreshBundle\Mapping\Loader;

use SofaScore\CacheRefreshBundle\Mapping\MappingCollection;

interface LoaderInterface
{
    /**
     * @return MappingCollection
     */
    public function load();
}
