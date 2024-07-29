<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Cache\TargetResolver;

use Sofascore\PurgatoryBundle\Attribute\Target\ForProperties;
use Sofascore\PurgatoryBundle\Attribute\Target\TargetInterface;
use Sofascore\PurgatoryBundle\Cache\RouteMetadata\RouteMetadata;

/**
 * @implements TargetResolverInterface<ForProperties>
 */
final class ForPropertiesResolver implements TargetResolverInterface
{
    /**
     * {@inheritDoc}
     */
    public static function for(): string
    {
        return ForProperties::class;
    }

    /**
     * {@inheritDoc}
     */
    public function resolve(TargetInterface $target, RouteMetadata $routeMetadata): array
    {
        return $target->properties;
    }
}
