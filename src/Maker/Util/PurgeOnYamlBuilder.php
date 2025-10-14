<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Maker\Util;

use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\DynamicValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\EnumValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\PropertyValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\RawValues;
use Sofascore\PurgatoryBundle\Attribute\Target\ForGroups;
use Sofascore\PurgatoryBundle\Listener\Enum\Action;

/**
 * @internal
 */
final class PurgeOnYamlBuilder implements PurgeOnBuilderInterface
{
    private bool $generated = false;

    private bool $targetAdded = false;
    private bool $routeParamsAdded = false;
    private bool $ifAdded = false;
    private bool $routeAdded = false;
    private bool $actionsAdded = false;

    private string $purgeOn;

    public function __construct(
        string $entity,
        private readonly bool $includeRouteName,
    ) {
        $this->purgeOn = "  - class: $entity\n";
    }

    public function generate(): string
    {
        if ($this->generated) {
            return $this->purgeOn;
        }

        if (!$this->routeAdded && $this->includeRouteName) {
            throw new \RuntimeException('Can not generate purge rule without route name');
        }

        $this->generated = true;

        return $this->purgeOn;
    }

    /**
     * {@inheritDoc}
     */
    public function addTarget(string|array|ForGroups $target): self
    {
        if ($this->generated) {
            throw new \RuntimeException('PurgeOn already generated');
        }

        if ($this->targetAdded) {
            throw new \RuntimeException('Target already added');
        }

        $this->targetAdded = true;

        if (\is_string($target)) {
            $this->purgeOn .= "    target: $target\n";
        } elseif (\is_array($target)) {
            $values = implode(', ', $target);
            $this->purgeOn .= "    target: [ $values ]\n";
        } else {
            $values = implode(', ', $target->groups);
            $this->purgeOn .= "    target: !for_groups [ $values ]\n";
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function addRouteParams(array $routeParams): self
    {
        if ($this->generated) {
            throw new \RuntimeException('PurgeOn already generated');
        }

        if ($this->routeParamsAdded) {
            throw new \RuntimeException('Route params already added');
        }

        $this->routeParamsAdded = true;

        $this->purgeOn .= "    route_params:\n";
        foreach ($routeParams as $routeParam => $type) {
            $this->purgeOn .= "      $routeParam: ";
            if (PropertyValues::class === $type) {
                $this->purgeOn .= "\n";
                continue;
            }

            $this->purgeOn .= match ($type) {
                RawValues::class => "!raw \n",
                EnumValues::class => "!enum \n",
                DynamicValues::class => "!dynamic \n",
            };
        }

        return $this;
    }

    public function includeIf(): self
    {
        if ($this->generated) {
            throw new \RuntimeException('PurgeOn already generated');
        }

        if ($this->ifAdded) {
            throw new \RuntimeException('If expression already added');
        }

        $this->ifAdded = true;

        $this->purgeOn .= "    if: ''\n";

        return $this;
    }

    public function addRoute(string $route): self
    {
        if ($this->generated) {
            throw new \RuntimeException('PurgeOn already generated');
        }

        if ($this->routeAdded) {
            throw new \RuntimeException('Route already added');
        }

        $this->routeAdded = true;

        if ($this->includeRouteName) {
            $this->purgeOn = "$route:\n".$this->purgeOn;
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function addActions(string|array $actions): self
    {
        if ($this->generated) {
            throw new \RuntimeException('PurgeOn already generated');
        }

        if ($this->actionsAdded) {
            throw new \RuntimeException('Actions already added');
        }

        $this->actionsAdded = true;

        if (\is_string($actions)) {
            $this->purgeOn .= "    actions: {$this->getActionValue($actions)}\n";
        } else {
            $values = implode(
                ', ',
                array_map(fn (string $actionName): string => $this->getActionValue($actionName), $actions),
            );
            $this->purgeOn .= "    actions: [ $values ]\n";
        }

        return $this;
    }

    private function getActionValue(string $actionName): string
    {
        foreach (Action::cases() as $action) {
            if ($action->name === $actionName) {
                return $action->value;
            }
        }

        throw new \RuntimeException("Invalid action '$actionName'");
    }
}
