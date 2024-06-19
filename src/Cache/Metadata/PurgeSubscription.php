<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Cache\Metadata;

use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\ValuesInterface;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Routing\Route;

final class PurgeSubscription
{
    /**
     * @param class-string                   $class
     * @param array<string, ValuesInterface> $routeParams
     */
    public function __construct(
        public readonly string $class,
        public readonly ?string $property,
        public readonly array $routeParams,
        public readonly string $routeName,
        public readonly Route $route,
        public readonly ?Expression $if = null,
    ) {
    }
}
