<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\DependencyInjection\Fixtures;

use Doctrine\Persistence\Mapping\ClassMetadata;
use Sofascore\PurgatoryBundle\Cache\PropertyResolver\SubscriptionResolverInterface;
use Sofascore\PurgatoryBundle\Cache\RouteMetadata\RouteMetadata;

final class DummySubscriptionResolver implements SubscriptionResolverInterface
{
    /**
     * {@inheritDoc}
     */
    public function resolveSubscription(
        RouteMetadata $routeMetadata,
        ClassMetadata $classMetadata,
        array $routeParams,
        string $target,
    ): \Generator {
        return true;
    }
}
