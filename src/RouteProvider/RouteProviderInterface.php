<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\RouteProvider;

use Sofascore\PurgatoryBundle2\Listener\Enum\Action;

/**
 * @template T of object
 */
interface RouteProviderInterface
{
    /**
     * @param T                                  $entity
     * @param array<string, array{mixed, mixed}> $entityChangeSet
     *
     * @return iterable<int, array{routeName: string, routeParams: array<string, ?scalar>}>
     */
    public function provideRoutesFor(
        Action $action,
        object $entity,
        array $entityChangeSet,
    ): iterable;

    public function supports(Action $action, object $entity): bool;
}
