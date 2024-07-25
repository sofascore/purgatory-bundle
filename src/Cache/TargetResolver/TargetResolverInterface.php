<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Cache\TargetResolver;

use Sofascore\PurgatoryBundle\Attribute\Target\TargetInterface;
use Sofascore\PurgatoryBundle\Cache\RouteMetadata\RouteMetadata;

interface TargetResolverInterface
{
    /**
     * @return class-string<TargetInterface>
     */
    public static function for(): string;

    /**
     * @return list<string>
     */
    public function resolve(TargetInterface $target, RouteMetadata $routeMetadata): array;
}
