<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\RouteProvider;

use Sofascore\PurgatoryBundle\Listener\Enum\Action;

/**
 * @template T of object
 */
interface RouteProviderInterface
{
    /**
     * @param T                                  $entity
     * @param array<string, array{mixed, mixed}> $entityChangeSet
     *
     * @return iterable<int, PurgeRoute>
     */
    public function provideRoutesFor(
        Action $action,
        object $entity,
        array $entityChangeSet,
    ): iterable;

    public function supports(Action $action, object $entity): bool;
}
