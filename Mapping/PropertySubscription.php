<?php

namespace SofaScore\Purgatory\Mapping;

use Symfony\Component\Routing\Route;

class PropertySubscription
{
    protected string $class;
    protected ?string $property = null;
    protected ?array $parameters = null;
    protected ?string $if = null;
    protected string $routeName;
    protected Route $route;
    protected ?array $tags = null;

    public function __construct(string $class, string $property = null)
    {
        $this->class = $class;
        $this->property = $property;
    }

    public function getClass()
    {
        return $this->class;
    }

    public function setClass(string $class): void
    {
        $this->class = $class;
    }

    public function getProperty(): ?string
    {
        return $this->property;
    }

    public function setProperty(?string $property): void
    {
        $this->property = $property;
    }

    public function getParameters(): ?array
    {
        return $this->parameters;
    }

    public function setParameters(?array $parameters): void
    {
        $this->parameters = $parameters;
    }

    public function getRouteName(): string
    {
        return $this->routeName;
    }

    public function setRouteName(string $routeName): void
    {
        $this->routeName = $routeName;
    }

    public function getRoute(): Route
    {
        return $this->route;
    }

    public function setRoute(Route $route): void
    {
        $this->route = $route;
    }

    public function getIf(): ?string
    {
        return $this->if;
    }

    public function setIf(?string $if): void
    {
        $this->if = $if;
    }

    public function getTags(): ?array
    {
        return $this->tags;
    }

    public function setTags(?array $tags): void
    {
        $this->tags = $tags;
    }
}
