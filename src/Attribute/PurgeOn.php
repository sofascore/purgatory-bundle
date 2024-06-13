<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Attribute;

use Sofascore\PurgatoryBundle2\Attribute\Target\ForProperties;
use Sofascore\PurgatoryBundle2\Attribute\Target\TargetInterface;
use Symfony\Component\ExpressionLanguage\Expression;

#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final class PurgeOn
{
    public readonly ?TargetInterface $target;
    /** @var ?non-empty-list<string> */
    public readonly ?array $route;

    /**
     * @param class-string                                       $class
     * @param string|non-empty-list<string>|TargetInterface|null $target
     * @param array<string, string|non-empty-list<string>>|null  $routeParams
     * @param string|non-empty-list<string>|null                 $route
     */
    public function __construct(
        public readonly string $class,
        string|array|TargetInterface|null $target = null,
        public readonly ?array $routeParams = null,
        public readonly ?Expression $if = null,
        string|array|null $route = null,
    ) {
        $this->target = \is_array($target) || \is_string($target) ? new ForProperties($target) : $target;
        $this->route = \is_string($route) ? [$route] : $route;
    }
}
