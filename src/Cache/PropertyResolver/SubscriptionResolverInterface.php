<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Cache\PropertyResolver;

use Doctrine\Persistence\Mapping\ClassMetadata;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\ValuesInterface;
use Sofascore\PurgatoryBundle\Cache\RouteMetadata\RouteMetadata;
use Sofascore\PurgatoryBundle\Cache\Subscription\PurgeSubscription;

interface SubscriptionResolverInterface
{
    /**
     * @param array<string, ValuesInterface> $routeParams
     * @param ClassMetadata<object>          $classMetadata
     *
     * @return \Generator<int, PurgeSubscription, mixed, bool>
     */
    public function resolveSubscription(
        RouteMetadata $routeMetadata,
        ClassMetadata $classMetadata,
        array $routeParams,
        string $target,
    ): \Generator;
}
