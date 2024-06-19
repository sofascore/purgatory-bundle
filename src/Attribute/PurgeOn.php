<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Attribute;

use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\PropertyValues;
use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\ValuesInterface;
use Sofascore\PurgatoryBundle2\Attribute\Target\ForProperties;
use Sofascore\PurgatoryBundle2\Attribute\Target\TargetInterface;
use Sofascore\PurgatoryBundle2\Listener\Enum\Action;
use Symfony\Component\ExpressionLanguage\Expression;

#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final class PurgeOn
{
    public readonly ?TargetInterface $target;
    /** @var ?non-empty-array<string, ValuesInterface> */
    public readonly ?array $routeParams;
    /** @var ?non-empty-list<string> */
    public readonly ?array $route;
    /** @var ?non-empty-list<Action> */
    public readonly ?array $actions;

    /**
     * @param class-string                                                            $class
     * @param string|non-empty-list<string>|TargetInterface|null                      $target
     * @param ?non-empty-array<string, string|non-empty-list<string>|ValuesInterface> $routeParams
     * @param string|non-empty-list<string>|null                                      $route
     * @param Action|non-empty-list<Action>|null                                      $actions
     */
    public function __construct(
        public readonly string $class,
        string|array|TargetInterface|null $target = null,
        ?array $routeParams = null,
        public readonly ?Expression $if = null,
        string|array|null $route = null,
        Action|array|null $actions = null,
    ) {
        $this->target = \is_array($target) || \is_string($target) ? new ForProperties($target) : $target;
        $this->routeParams = null !== $routeParams ? self::normalizeRouteParams($routeParams) : null;
        $this->route = \is_string($route) ? [$route] : $route;
        $this->actions = $actions instanceof Action ? [$actions] : $actions;
    }

    /**
     * @param non-empty-array<string, string|non-empty-list<string>|ValuesInterface> $routeParams
     *
     * @return non-empty-array<string, ValuesInterface>
     */
    private static function normalizeRouteParams(array $routeParams): array
    {
        /** @var array<string, ValuesInterface> $normalized */
        $normalized = [];
        foreach ($routeParams as $key => $value) {
            $normalized[$key] = self::normalizeValue($value);
        }

        return $normalized;
    }

    /**
     * @param string|non-empty-list<string>|ValuesInterface $value
     */
    public static function normalizeValue(string|array|ValuesInterface $value): ValuesInterface
    {
        return !$value instanceof ValuesInterface
            ? new PropertyValues(...(\is_array($value) ? $value : [$value]))
            : $value;
    }
}
