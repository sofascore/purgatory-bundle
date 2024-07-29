<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Cache\TargetResolver;

use Sofascore\PurgatoryBundle\Attribute\Target\TargetInterface;
use Sofascore\PurgatoryBundle\Cache\RouteMetadata\RouteMetadata;

/**
 * @template T of TargetInterface
 */
interface TargetResolverInterface
{
    /**
     * @return class-string<T>
     */
    public static function for(): string;

    /**
     * @param T $target
     *
     * @return list<string>
     */
    public function resolve(TargetInterface $target, RouteMetadata $routeMetadata): array;
}
