<?php

namespace Sofascore\PurgatoryBundle\Mapping\Loader;

use Sofascore\PurgatoryBundle\Mapping\MappingCollection;

interface LoaderInterface
{
    public function load(): MappingCollection;
}
