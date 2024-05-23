<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Attribute;

use Sofascore\PurgatoryBundle2\Attribute\Target\TargetInterface;
use Symfony\Component\ExpressionLanguage\Expression;

#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_METHOD)]
final class PurgeOn
{
    /**
     * @param class-string                            $class
     * @param array<string, string|list<string>>|null $routeParams
     * @param string|list<string>|null                $route
     */
    public function __construct(
        public readonly string $class,
        public readonly ?TargetInterface $target = null,
        public readonly ?array $routeParams = null,
        public readonly ?Expression $if = null,
        public readonly string|array|null $route = null,
    ) {
    }
}
