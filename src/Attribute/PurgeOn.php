<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Attribute;

use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\PropertyValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\ValuesInterface;
use Sofascore\PurgatoryBundle\Attribute\Target\ForProperties;
use Sofascore\PurgatoryBundle\Attribute\Target\TargetInterface;
use Sofascore\PurgatoryBundle\Exception\LogicException;
use Sofascore\PurgatoryBundle\Listener\Enum\Action;
use Symfony\Component\ExpressionLanguage\Expression;

#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final class PurgeOn
{
    public readonly ?TargetInterface $target;
    /** @var ?non-empty-array<string, ValuesInterface> */
    public readonly ?array $routeParams;
    public readonly ?Expression $if;
    /** @var ?non-empty-list<string> */
    public readonly ?array $route;
    /** @var ?non-empty-list<Action> */
    public readonly ?array $actions;

    /**
     * @param class-string                                                            $class
     * @param string|non-empty-list<string>|TargetInterface|null                      $target
     * @param ?non-empty-array<string, string|non-empty-list<string>|ValuesInterface> $routeParams
     * @param string|non-empty-list<string>|null                                      $route
     * @param value-of<Action>|non-empty-list<value-of<Action>|Action>|Action|null    $actions
     */
    public function __construct(
        public readonly string $class,
        string|array|TargetInterface|null $target = null,
        ?array $routeParams = null,
        string|Expression|null $if = null,
        string|array|null $route = null,
        string|array|Action|null $actions = null,
    ) {
        $this->target = \is_array($target) || \is_string($target) ? new ForProperties($target) : $target;
        $this->routeParams = null !== $routeParams ? self::normalizeRouteParams($routeParams) : null;
        $this->if = \is_string($if) ? self::normalizeExpression($if) : $if;
        $this->route = \is_string($route) ? [$route] : $route;
        $this->actions = null !== $actions ? self::normalizeActions($actions) : null;
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

    private static function normalizeExpression(string $if): Expression
    {
        if (!class_exists(Expression::class)) {
            throw new LogicException('You cannot use the "if" attribute because the Symfony ExpressionLanguage component is not installed. Try running "composer require symfony/expression-language".');
        }

        return new Expression($if);
    }

    /**
     * @param value-of<Action>|non-empty-list<value-of<Action>|Action>|Action $actions
     *
     * @return non-empty-list<Action>
     */
    private static function normalizeActions(string|array|Action $actions): array
    {
        if (!\is_array($actions)) {
            $actions = [$actions];
        }

        $normalized = [];
        foreach ($actions as $action) {
            $normalized[] = $action instanceof Action ? $action : Action::from($action);
        }

        return $normalized;
    }
}
