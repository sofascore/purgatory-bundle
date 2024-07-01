<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Cache\Subscription;

use Doctrine\Persistence\ManagerRegistry;
use Psr\Container\ContainerInterface;
use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\PropertyValues;
use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\ValuesInterface;
use Sofascore\PurgatoryBundle2\Cache\ControllerMetadata\ControllerMetadataProviderInterface;
use Sofascore\PurgatoryBundle2\Cache\PropertyResolver\SubscriptionResolverInterface;
use Sofascore\PurgatoryBundle2\Cache\TargetResolver\TargetResolverInterface;
use Sofascore\PurgatoryBundle2\Exception\EntityMetadataNotFoundException;
use Sofascore\PurgatoryBundle2\Exception\TargetSubscriptionNotResolvableException;

/**
 * @internal Used during cache warmup
 */
final class PurgeSubscriptionProvider implements PurgeSubscriptionProviderInterface
{
    /**
     * @param iterable<SubscriptionResolverInterface> $subscriptionResolvers
     */
    public function __construct(
        private readonly iterable $subscriptionResolvers,
        private readonly ControllerMetadataProviderInterface $controllerMetadataProvider,
        private readonly ManagerRegistry $managerRegistry,
        private readonly ContainerInterface $targetResolverLocator,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function provide(): iterable
    {
        foreach ($this->controllerMetadataProvider->provide() as $controllerMetadata) {
            $purgeOn = $controllerMetadata->purgeOn;

            // if route parameters are not specified, they are same as path variables
            if (null === $purgeOn->routeParams) {
                /** @var list<string> $pathVariables */
                $pathVariables = $controllerMetadata->route->compile()->getPathVariables();

                /** @var array<string, ValuesInterface> $routeParams */
                $routeParams = [];

                foreach ($pathVariables as $pathVariable) {
                    $routeParams[$pathVariable] = new PropertyValues($pathVariable);
                }
            } else {
                $routeParams = $purgeOn->routeParams;
            }

            if (null === $purgeOn->target) {
                yield new PurgeSubscription(
                    class: $purgeOn->class,
                    property: null,
                    routeParams: $routeParams,
                    routeName: $controllerMetadata->routeName,
                    route: $controllerMetadata->route,
                    actions: $purgeOn->actions,
                    if: $purgeOn->if,
                );

                continue;
            }

            $class = $purgeOn->class;

            if (null === $entityMetadata = $this->managerRegistry->getManagerForClass($class)?->getClassMetadata($class)) {
                throw new EntityMetadataNotFoundException($class);
            }

            /** @var TargetResolverInterface $targetResolver */
            $targetResolver = $this->targetResolverLocator->get($purgeOn->target::class);

            foreach ($targetResolver->resolve($purgeOn->target, $controllerMetadata) as $property) {
                $targetResolved = false;

                foreach ($this->subscriptionResolvers as $resolver) {
                    yield from $subscriptions = $resolver->resolveSubscription($controllerMetadata, $entityMetadata, $routeParams, $property);

                    if (true === $subscriptions->getReturn()) {
                        $targetResolved = true;
                    }
                }

                if (!$targetResolved) {
                    throw new TargetSubscriptionNotResolvableException($controllerMetadata->routeName, $class, $property);
                }
            }
        }
    }
}
