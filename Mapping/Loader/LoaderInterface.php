<?php

namespace SofaScore\CacheRefreshBundle\Mapping\Loader;

use SofaScore\CacheRefreshBundle\Mapping\MappingCollection;

interface LoaderInterface
{
    public function load(): MappingCollection;
}
