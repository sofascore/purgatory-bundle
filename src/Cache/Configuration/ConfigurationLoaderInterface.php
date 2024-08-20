<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Cache\Configuration;

interface ConfigurationLoaderInterface
{
    public function load(): Configuration;
}
