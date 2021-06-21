<?php

namespace SofaScore\Purgatory\Mapping\Loader;

use SofaScore\Purgatory\Mapping\MappingCollection;

interface LoaderInterface
{
    public function load(): MappingCollection;
}
