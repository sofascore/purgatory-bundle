<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\PurgeRouteGenerator;

use Sofascore\PurgatoryBundle2\Listener\Enum\Action;

interface PurgeRouteGeneratorInterface
{
    /**
     * @param array<string, array{mixed, mixed}> $entityChangeSet
     *
     * @return iterable<int, array{routeName: string, routeParams: array<string, scalar>}>
     */
    public function getRoutesToPurge(
        Action $action,
        object $entity,
        array $entityChangeSet,
    ): iterable;

    public function supports(Action $action, object $entity): bool;
}
