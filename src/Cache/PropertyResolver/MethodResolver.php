<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Cache\PropertyResolver;

use Doctrine\Persistence\Mapping\ClassMetadata;
use Sofascore\PurgatoryBundle2\Attribute\TargetedProperties;
use Sofascore\PurgatoryBundle2\Cache\ControllerMetadata\ControllerMetadata;
use Sofascore\PurgatoryBundle2\Exception\TargetSubscriptionNotResolvableException;
use Symfony\Component\PropertyInfo\PropertyReadInfo;
use Symfony\Component\PropertyInfo\PropertyReadInfoExtractorInterface;

/**
 * Handles methods with the {@see TargetedProperties} attribute.
 */
final class MethodResolver implements SubscriptionResolverInterface
{
    /**
     * @param iterable<SubscriptionResolverInterface> $subscriptionResolvers
     */
    public function __construct(
        private readonly iterable $subscriptionResolvers,
        private readonly PropertyReadInfoExtractorInterface $extractor,
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
        $purgeOn = $controllerMetadata->purgeOn;

        $method = $target;
        if (!method_exists($purgeOn->class, $method)) {
            $readInfo = $this->extractor
                ->getReadInfo(
                    class: $purgeOn->class,
                    property: $target,
                );

            if (null === $readInfo) {
                return false;
            }

            if (PropertyReadInfo::TYPE_METHOD !== $readInfo->getType()) {
                return false;
            }

            $method = $readInfo->getName();
        }

        $reflection = new \ReflectionMethod($purgeOn->class, $method);

        if (!$reflectionAttribute = $reflection->getAttributes(TargetedProperties::class)) {
            return false;
        }

        /** @var TargetedProperties $targetedProperties */
        $targetedProperties = $reflectionAttribute[0]->newInstance();

        foreach ($targetedProperties->target as $targetProperty) {
            $targetResolved = false;

            foreach ($this->subscriptionResolvers as $resolver) {
                yield from $subscriptions = $resolver->resolveSubscription($controllerMetadata, $classMetadata, $routeParams, $targetProperty);

                if (true === $subscriptions->getReturn()) {
                    $targetResolved = true;
                }
            }

            if (!$targetResolved) {
                throw new TargetSubscriptionNotResolvableException($controllerMetadata->routeName, $purgeOn->class, $targetProperty);
            }
        }

        return true;
    }
}
