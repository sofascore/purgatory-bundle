<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Cache\PropertyResolver;

use Doctrine\ORM\Mapping\ClassMetadata as ORMClassMetadata;
use Doctrine\ORM\Mapping\EmbeddedClassMapping;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Sofascore\PurgatoryBundle2\Cache\Metadata\ControllerMetadata;
use Sofascore\PurgatoryBundle2\Cache\Metadata\PurgeSubscription;
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
        ControllerMetadata $controllerMetadata,
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
                class: $controllerMetadata->purgeOn->class,
                property: $target.'.'.$field,
                routeParams: $routeParams,
                routeName: $controllerMetadata->routeName,
                route: $controllerMetadata->route,
                actions: $controllerMetadata->purgeOn->actions,
                if: $controllerMetadata->purgeOn->if,
            );
        }

        return true;
    }
}
