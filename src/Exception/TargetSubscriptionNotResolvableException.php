<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Exception;

final class TargetSubscriptionNotResolvableException extends RuntimeException
{
    private const MESSAGE = 'Unable to resolve subscription for target "%s::%s" and route "%s".';

    public function __construct(
        public readonly string $routeName,
        public readonly string $className,
        public readonly string $target,
    ) {
        parent::__construct(
            message: \sprintf(self::MESSAGE, $className, $target, $routeName),
        );
    }
}
