<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\DependencyInjection\Fixtures;

use Sofascore\PurgatoryBundle\Listener\Enum\Action;
use Sofascore\PurgatoryBundle\RouteProvider\RouteProviderInterface;

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
