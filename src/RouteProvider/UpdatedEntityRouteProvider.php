<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\RouteProvider;

use Sofascore\PurgatoryBundle2\Listener\Enum\Action;

/**
 * @TODO Handle before and after urls
 */
final class UpdatedEntityRouteProvider extends AbstractEntityRouteProvider
{
    public function supports(Action $action, object $entity): bool
    {
        return Action::Update === $action;
    }

    /**
     * {@inheritDoc}
     */
    protected function getChangedProperties(object $entity, array $entityChangeSet): array
    {
        return array_keys($entityChangeSet);
    }
}
