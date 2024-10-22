<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Cache\PropertyResolver;

use Doctrine\ORM\Mapping\ClassMetadata as ORMClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Sofascore\PurgatoryBundle\Cache\RouteMetadata\RouteMetadata;
use Sofascore\PurgatoryBundle\Cache\Subscription\PurgeSubscription;
use Sofascore\PurgatoryBundle\Exception\EntityMetadataNotFoundException;

/**
 * Handles fields and association properties.
 */
final class PropertyResolver implements SubscriptionResolverInterface
{
    /**
     * @var array<class-string, list<ClassMetadata<object>>>
     */
    private array $mappedSuperclassChildren = [];

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
            context: $routeMetadata->purgeOn->context,
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
            if ($this->isValidSubClassTarget($target, $classMetadata)) {
                return true;
            }

            if ($this->isValidMappedSuperclassTarget($target, $classMetadata)) {
                return true;
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

    /**
     * @param ORMClassMetadata<object> $classMetadata
     */
    private function isValidSubClassTarget(string $target, ORMClassMetadata $classMetadata): bool
    {
        foreach ($classMetadata->subClasses as $subClass) {
            if (null === $subClassMetadata = $this->managerRegistry->getManagerForClass($subClass)?->getClassMetadata($subClass)) {
                throw new EntityMetadataNotFoundException($subClass);
            }

            if ($this->isValidTarget($target, $subClassMetadata)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param ORMClassMetadata<object> $classMetadata
     */
    private function isValidMappedSuperclassTarget(string $target, ORMClassMetadata $classMetadata): bool
    {
        if (!$classMetadata->isMappedSuperclass) {
            return false;
        }

        foreach ($this->getChildrenMetadata($classMetadata->getName()) as $metadata) {
            if ($this->isValidTarget($target, $metadata)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param class-string $parentClass
     *
     * @return list<ClassMetadata<object>>
     */
    private function getChildrenMetadata(string $parentClass): array
    {
        if (isset($this->mappedSuperclassChildren[$parentClass])) {
            return $this->mappedSuperclassChildren[$parentClass];
        }

        $this->mappedSuperclassChildren[$parentClass] = [];

        foreach ($this->managerRegistry->getManagers() as $manager) {
            foreach ($manager->getMetadataFactory()->getAllMetadata() as $metadata) {
                if (!is_subclass_of($metadata->getName(), $parentClass)) {
                    continue;
                }

                $this->mappedSuperclassChildren[$parentClass][] = $metadata;
            }
        }

        return $this->mappedSuperclassChildren[$parentClass];
    }
}
