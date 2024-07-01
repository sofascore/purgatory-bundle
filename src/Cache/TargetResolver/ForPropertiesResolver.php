<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Cache\TargetResolver;

use Sofascore\PurgatoryBundle2\Attribute\Target\ForProperties;
use Sofascore\PurgatoryBundle2\Attribute\Target\TargetInterface;
use Sofascore\PurgatoryBundle2\Cache\ControllerMetadata\ControllerMetadata;
use Sofascore\PurgatoryBundle2\Exception\InvalidArgumentException;

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
    public function resolve(TargetInterface $target, ControllerMetadata $controllerMetadata): array
    {
        if (!$target instanceof ForProperties) {
            throw new InvalidArgumentException(sprintf('Target must be an instance of "%s".', ForProperties::class));
        }

        return $target->properties;
    }
}
