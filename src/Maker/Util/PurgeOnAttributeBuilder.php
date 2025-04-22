<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Maker\Util;

use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\DynamicValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\EnumValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\PropertyValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\RawValues;
use Sofascore\PurgatoryBundle\Attribute\Target\ForGroups;

/**
 * @internal
 */
final class PurgeOnAttributeBuilder implements PurgeOnBuilderInterface
{
    private bool $generated = false;

    private bool $targetAdded = false;
    private bool $routeParamsAdded = false;
    private bool $ifAdded = false;
    private bool $routeAdded = false;
    private bool $actionsAdded = false;

    private ?string $forGroupsAlias = null;
    private ?string $rawValuesAlias = null;
    private ?string $enumValuesAlias = null;
    private ?string $dynamicValuesAlias = null;
    private ?string $actionsAlias = null;

    private string $purgeOn;

    public function __construct(
        private readonly string $purgeOnAlias,
        private readonly string $entityAlias,
    ) {
        $this->purgeOn = "    #[$this->purgeOnAlias($this->entityAlias::class,";
    }

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
            $this->purgeOn .= "\n        target: '$target',";
        } elseif (\is_array($target)) {
            $values = implode(', ', array_map(static fn (string $t): string => "'$t'", $target));
            $this->purgeOn .= "\n        target: [$values],";
        } else {
            if (null === $this->forGroupsAlias) {
                throw new \RuntimeException('ForGroups alias is missing');
            }

            $values = implode(', ', array_map(static fn (string $t): string => "'$t'", $target->groups));
            $this->purgeOn .= "\n        target: new $this->forGroupsAlias([$values]),";
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

        $this->purgeOn .= "\n        routeParams: [";
        foreach ($routeParams as $routeParam => $type) {
            $this->purgeOn .= "\n            '$routeParam' => ";
            if (PropertyValues::class === $type) {
                $this->purgeOn .= "'',";
                continue;
            }

            $alias = match ($type) {
                RawValues::class => $this->rawValuesAlias ?? throw new \RuntimeException('RawValues alias is missing'),
                EnumValues::class => $this->enumValuesAlias ?? throw new \RuntimeException('EnumValues alias is missing'),
                DynamicValues::class => $this->dynamicValuesAlias ?? throw new \RuntimeException('DynamicValues alias is missing'),
            };

            $this->purgeOn .= "new $alias(),";
        }
        $this->purgeOn .= "\n        ],";

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

        $this->purgeOn .= "\n        if: '',";

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

        $this->purgeOn .= "\n        route: '$route',";

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

        if (null === $this->actionsAlias) {
            throw new \RuntimeException('Actions alias is missing');
        }

        $this->actionsAdded = true;

        if (\is_string($actions)) {
            $this->purgeOn .= "\n        actions: $this->actionsAlias::$actions,";
        } else {
            $values = implode(', ', array_map(fn (string $action): string => "$this->actionsAlias::$action", $actions));
            $this->purgeOn .= "\n        actions: [$values],";
        }

        return $this;
    }

    public function generate(): string
    {
        if ($this->generated) {
            return $this->purgeOn;
        }

        if ($this->isOneLiner()) {
            $this->purgeOn = "    #[$this->purgeOnAlias($this->entityAlias::class)]";
        } else {
            $this->purgeOn .= "\n    )]";
        }

        $this->generated = true;

        return $this->purgeOn;
    }

    public function setForGroupsAlias(string $alias): void
    {
        $this->forGroupsAlias = $alias;
    }

    public function setRawValuesAlias(string $alias): void
    {
        $this->rawValuesAlias = $alias;
    }

    public function setEnumValuesAlias(string $alias): void
    {
        $this->enumValuesAlias = $alias;
    }

    public function setDynamicValuesAlias(string $alias): void
    {
        $this->dynamicValuesAlias = $alias;
    }

    public function setActionsAlias(string $alias): void
    {
        $this->actionsAlias = $alias;
    }

    private function isOneLiner(): bool
    {
        return !$this->targetAdded
            && !$this->routeParamsAdded
            && !$this->ifAdded
            && !$this->routeAdded
            && !$this->actionsAdded;
    }
}
