<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\RouteProvider;

use Sofascore\PurgatoryBundle2\Listener\Enum\Action;

final class CreatedEntityRouteProvider extends AbstractEntityRouteProvider
{
    /**
     * {@inheritDoc}
     */
    public function supports(Action $action, object $entity): bool
    {
        return Action::Create === $action;
    }

    protected function getChangedProperties(object $entity, array $entityChangeSet): array
    {
        return array_keys($entityChangeSet);
    }
}
