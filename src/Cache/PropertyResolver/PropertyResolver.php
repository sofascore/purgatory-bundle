<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Cache\PropertyResolver;

use Doctrine\Persistence\Mapping\ClassMetadata;
use Sofascore\PurgatoryBundle2\Cache\ControllerMetadata\ControllerMetadata;
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
        ControllerMetadata $controllerMetadata,
        ClassMetadata $classMetadata,
        array $routeParams,
        string $target,
    ): \Generator {
        if (!$this->isValidTarget($target, $classMetadata)) {
            return false;
        }

        yield new PurgeSubscription(
            class: $controllerMetadata->purgeOn->class,
            property: $target,
            routeParams: $routeParams,
            routeName: $controllerMetadata->routeName,
            route: $controllerMetadata->route,
            actions: $controllerMetadata->purgeOn->actions,
            if: $controllerMetadata->purgeOn->if,
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
