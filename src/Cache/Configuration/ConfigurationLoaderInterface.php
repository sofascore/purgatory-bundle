<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Cache\Configuration;

use Sofascore\PurgatoryBundle\Listener\Enum\Action;

interface ConfigurationLoaderInterface
{
    /**
     * @return array<class-string|non-falsy-string, list<array{
     *     routeName: string,
     *     routeParams?: array<string, array{type: string, values: list<mixed>, optional?: true}>,
     *     if?: string,
     *     actions?: non-empty-list<Action>,
     * }>>
     */
    public function load(): array;
}
