<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Cache\Metadata;

use Sofascore\PurgatoryBundle2\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle2\Attribute\Target\ForGroups;
use Sofascore\PurgatoryBundle2\Attribute\Target\ForProperties;

/**
 * @internal Used during cache warmup
 */
final class PurgeSubscriptionProvider implements PurgeSubscriptionProviderInterface
{
    public function __construct(
        private readonly ControllerMetadataProviderInterface $controllerMetadataProvider,
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
                $pathVariables = $controllerMetadata->route->compile()->getPathVariables();

                $routeParams = array_combine($pathVariables, $pathVariables);
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
                    if: $purgeOn->if,
                );

                continue;
            }

            foreach ($this->getPropertiesFromPurgeOn($purgeOn) as $property) {
                yield new PurgeSubscription(
                    class: $purgeOn->class,
                    property: $property,
                    routeParams: $routeParams,
                    routeName: $controllerMetadata->routeName,
                    route: $controllerMetadata->route,
                    if: $purgeOn->if,
                );
            }
        }
    }

    private function getPropertiesFromPurgeOn(PurgeOn $purgeOn): array
    {
        if ($purgeOn->target instanceof ForProperties) {
            return $purgeOn->target->properties;
        }

        if ($purgeOn->target instanceof ForGroups) {
            // TODO
            return [];
        }

        throw new \RuntimeException('Unsupported target');
    }
}
