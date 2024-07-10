<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\DependencyInjection\Fixtures;

use Sofascore\PurgatoryBundle2\Attribute\AsRouteParamService;

#[AsRouteParamService('alias_class')]
class DummyRouteParamService
{
    public function __invoke()
    {
    }

    #[AsRouteParamService('alias_foo')]
    public function foo()
    {
    }
}
