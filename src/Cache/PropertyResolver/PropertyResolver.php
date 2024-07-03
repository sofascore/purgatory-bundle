<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Cache\PropertyResolver;

use Doctrine\ORM\Mapping\ClassMetadata as ORMClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Sofascore\PurgatoryBundle2\Cache\RouteMetadata\RouteMetadata;
use Sofascore\PurgatoryBundle2\Cache\Subscription\PurgeSubscription;
use Sofascore\PurgatoryBundle2\Exception\EntityMetadataNotFoundException;

/**
 * Handles fields and association properties.
 */
final class PropertyResolver implements SubscriptionResolverInterface
{
    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
    ) {
    }

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

        if ($this->isValidAssociationTarget($target, $classMetadata)) {
            return true;
        }

        if ($classMetadata instanceof ORMClassMetadata) {
            foreach ($classMetadata->subClasses as $subClass) {
                if (null === $subClassMetadata = $this->managerRegistry->getManagerForClass($subClass)?->getClassMetadata($subClass)) {
                    throw new EntityMetadataNotFoundException($subClass);
                }

                if ($subClassMetadata->hasField($target)) {
                    return true;
                }

                if ($this->isValidAssociationTarget($target, $subClassMetadata)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param ClassMetadata<object> $classMetadata
     */
    private function isValidAssociationTarget(string $target, ClassMetadata $classMetadata): bool
    {
        return $classMetadata->hasAssociation($target)
            && $classMetadata->isSingleValuedAssociation($target)
            && !$classMetadata->isAssociationInverseSide($target);
    }
}
