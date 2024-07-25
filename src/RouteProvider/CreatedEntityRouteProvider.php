<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\RouteProvider;

use Sofascore\PurgatoryBundle\Listener\Enum\Action;

final class CreatedEntityRouteProvider extends AbstractEntityRouteProvider
{
    public function supports(Action $action, object $entity): bool
    {
        return Action::Create === $action;
    }

    /**
     * {@inheritDoc}
     */
    protected function getChangedProperties(object $entity, array $entityChangeSet): array
    {
        return array_keys($entityChangeSet);
    }
}
