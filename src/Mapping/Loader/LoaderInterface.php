<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Mapping\Loader;

use Sofascore\PurgatoryBundle\Mapping\MappingCollection;

interface LoaderInterface
{
    public function load(): MappingCollection;
}
