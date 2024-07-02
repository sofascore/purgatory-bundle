<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\DependencyInjection\Fixtures;

use Sofascore\PurgatoryBundle2\Attribute\Target\TargetInterface;
use Sofascore\PurgatoryBundle2\Cache\RouteMetadata\RouteMetadata;
use Sofascore\PurgatoryBundle2\Cache\TargetResolver\TargetResolverInterface;

final class DummyTargetResolver implements TargetResolverInterface
{
    /**
     * {@inheritDoc}
     */
    public static function for(): string
    {
        return '';
    }

    /**
     * {@inheritDoc}
     */
    public function resolve(TargetInterface $target, RouteMetadata $routeMetadata): array
    {
        return [];
    }
}
