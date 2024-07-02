<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Cache\PropertyResolver;

use Doctrine\Persistence\Mapping\ClassMetadata;
use Sofascore\PurgatoryBundle2\Cache\RouteMetadata\RouteMetadata;
use Sofascore\PurgatoryBundle2\Cache\Subscription\PurgeSubscription;

/**
 * Handles fields and association properties.
 */
final class PropertyResolver implements SubscriptionResolverInterface
{
    /**
     * {@inheritDoc}
     */
    public function resolveSubscription(
        RouteMetadata $routeMetadata,
        ClassMetadata $classMetadata,
        array $routeParams,
        string $target,
    ): \Generator {
        if (!$this->isValidTarget($target, $classMetadata)) {
            return false;
        }

        yield new PurgeSubscription(
            class: $routeMetadata->purgeOn->class,
            property: $target,
            routeParams: $routeParams,
            routeName: $routeMetadata->routeName,
            route: $routeMetadata->route,
            actions: $routeMetadata->purgeOn->actions,
            if: $routeMetadata->purgeOn->if,
        );

        return true;
    }

    /**
     * @param ClassMetadata<object> $classMetadata
     */
    private function isValidTarget(string $target, ClassMetadata $classMetadata): bool
    {
        if ($classMetadata->hasField($target)) {
            return true;
        }

        if (
            $classMetadata->hasAssociation($target)
            && $classMetadata->isSingleValuedAssociation($target)
            && !$classMetadata->isAssociationInverseSide($target)
        ) {
            return true;
        }

        return false;
    }
}
