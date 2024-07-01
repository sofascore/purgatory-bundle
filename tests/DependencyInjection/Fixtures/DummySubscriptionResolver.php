<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\DependencyInjection\Fixtures;

use Doctrine\Persistence\Mapping\ClassMetadata;
use Sofascore\PurgatoryBundle2\Cache\ControllerMetadata\ControllerMetadata;
use Sofascore\PurgatoryBundle2\Cache\PropertyResolver\SubscriptionResolverInterface;

final class DummySubscriptionResolver implements SubscriptionResolverInterface
{
    /**
     * {@inheritDoc}
     */
    public function resolveSubscription(
        ControllerMetadata $controllerMetadata,
        ClassMetadata $classMetadata,
        array $routeParams,
        string $target,
    ): \Generator {
        return true;
    }
}
