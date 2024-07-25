<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\DependencyInjection\Fixtures;

use Sofascore\PurgatoryBundle\Attribute\AsRouteParamService;

#[AsRouteParamService('alias_class')]
class DummyInvalidRouteParamService
{
}
