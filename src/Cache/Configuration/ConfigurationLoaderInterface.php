<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Cache\Configuration;

interface ConfigurationLoaderInterface
{
    /**
     * @return array<class-string|non-falsy-string, list<array{routeName: string, routeParams: array<string, string|list<string>>, if: ?string}>>
     */
    public function load(): array;
}
