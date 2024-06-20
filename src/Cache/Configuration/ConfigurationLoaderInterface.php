<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Cache\Configuration;

use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\ValuesInterface;
use Sofascore\PurgatoryBundle2\Listener\Enum\Action;

interface ConfigurationLoaderInterface
{
    /**
     * @return array<class-string|non-falsy-string, list<array{routeName: string, routeParams: array<string, array{type: class-string<ValuesInterface>, values: list<mixed>}>, if?: string, actions?: non-empty-list<Action>}>>
     */
    public function load(): array;
}
