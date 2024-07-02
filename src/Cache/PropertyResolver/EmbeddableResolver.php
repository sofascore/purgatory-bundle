<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Cache\PropertyResolver;

use Doctrine\ORM\Mapping\ClassMetadata as ORMClassMetadata;
use Doctrine\ORM\Mapping\EmbeddedClassMapping;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Sofascore\PurgatoryBundle2\Cache\RouteMetadata\RouteMetadata;
use Sofascore\PurgatoryBundle2\Cache\Subscription\PurgeSubscription;
use Sofascore\PurgatoryBundle2\Exception\EntityMetadataNotFoundException;

/**
 * Handles properties from embeddable objects.
 */
final class EmbeddableResolver implements SubscriptionResolverInterface
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
        if (!$classMetadata instanceof ORMClassMetadata || !isset($classMetadata->embeddedClasses[$target])) {
            return false;
        }

        /** @var EmbeddedClassMapping|array{class: class-string} $embeddedClassMapping */
        $embeddedClassMapping = $classMetadata->embeddedClasses[$target];

        $class = $embeddedClassMapping instanceof EmbeddedClassMapping
            ? $embeddedClassMapping->class
            : $embeddedClassMapping['class'];

        if (null === $embeddableMetadata = $this->managerRegistry->getManagerForClass($classMetadata->getName())?->getClassMetadata($class)) {
            throw new EntityMetadataNotFoundException($class);
        }

        foreach ($embeddableMetadata->getFieldNames() as $field) {
            yield new PurgeSubscription(
                class: $routeMetadata->purgeOn->class,
                property: $target.'.'.$field,
                routeParams: $routeParams,
                routeName: $routeMetadata->routeName,
                route: $routeMetadata->route,
                actions: $routeMetadata->purgeOn->actions,
                if: $routeMetadata->purgeOn->if,
            );
        }

        return true;
    }
}
