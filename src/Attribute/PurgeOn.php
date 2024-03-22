<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Attribute;

use Symfony\Component\ExpressionLanguage\Expression;

#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_METHOD)]
final class PurgeOn
{
    /**
     * @param class-string $class
     */
    public function __construct(
        public readonly string $class,
        public readonly ?array $target = null,
        public readonly ?array $routeParams = null,
        public readonly ?Expression $if = null,
        public readonly string|array|null $route = null,
    ) {
    }
}
