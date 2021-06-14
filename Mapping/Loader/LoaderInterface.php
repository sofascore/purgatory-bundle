<?php

namespace SofaScore\Purgatory\Mapping\Loader;

use SofaScore\Purgatory\Mapping\MappingCollection;

interface LoaderInterface
{
    /**
     * @return MappingCollection
     */
    public function load();
}
