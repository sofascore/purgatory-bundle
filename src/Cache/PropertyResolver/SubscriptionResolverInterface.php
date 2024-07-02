<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Cache\PropertyResolver;

use Doctrine\Persistence\Mapping\ClassMetadata;
use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\ValuesInterface;
use Sofascore\PurgatoryBundle2\Cache\RouteMetadata\RouteMetadata;
use Sofascore\PurgatoryBundle2\Cache\Subscription\PurgeSubscription;

interface SubscriptionResolverInterface
{
    /**
     * @param array<string, ValuesInterface> $routeParams
     * @param ClassMetadata<object>          $classMetadata
     *
     * @return \Generator<mixed, PurgeSubscription, mixed, bool>
     */
    public function resolveSubscription(
        RouteMetadata $routeMetadata,
        ClassMetadata $classMetadata,
        array $routeParams,
        string $target,
    ): \Generator;
}
