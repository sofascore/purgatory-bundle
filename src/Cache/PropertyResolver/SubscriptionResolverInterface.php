<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Cache\PropertyResolver;

use Doctrine\Persistence\Mapping\ClassMetadata;
use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\ValuesInterface;
use Sofascore\PurgatoryBundle2\Cache\Metadata\ControllerMetadata;
use Sofascore\PurgatoryBundle2\Cache\Metadata\PurgeSubscription;

interface SubscriptionResolverInterface
{
    /**
     * @param array<string, ValuesInterface> $routeParams
     * @param ClassMetadata<object>          $classMetadata
     *
     * @return \Generator<mixed, PurgeSubscription, mixed, bool>
     */
    public function resolveSubscription(
        ControllerMetadata $controllerMetadata,
        ClassMetadata $classMetadata,
        array $routeParams,
        string $target,
    ): \Generator;
}
