<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\DependencyInjection\Fixtures;

use Sofascore\PurgatoryBundle2\Listener\Enum\Action;
use Sofascore\PurgatoryBundle2\RouteProvider\RouteProviderInterface;

final class DummyRouteProvider implements RouteProviderInterface
{
    /**
     * {@inheritDoc}
     */
    public function provideRoutesFor(Action $action, object $entity, array $entityChangeSet): iterable
    {
        return [];
    }

    public function supports(Action $action, object $entity): bool
    {
        return true;
    }
}
