<?php

namespace SofaScore\Purgatory\Mapping;

use Symfony\Component\Routing\Route;

class PropertySubscription
{
    /**
     * @var string
     */
    protected $class;

    protected ?string $property;

    /**
     * @var array<array>
     */
    protected $parameters = [];

    /**
     * @var string
     */
    protected $priority;

    /**
     * @var string
     */
    protected $if;

    /**
     * @var string
     */
    protected $routeName;

    /**
     * @var Route
     */
    protected $route;

    /**
     * @var array
     */
    protected $tags;

    /**
     * @param class-string $class
     * @param string|null $property
     */
    public function __construct(string $class, string $property = null)
    {
        $this->class = $class;
        $this->property = $property;
    }

    public function getClass()
    {
        return $this->class;
    }

    public function setClass($class)
    {
        $this->class = $class;
    }

    public function getProperty()
    {
        return $this->property;
    }

    public function setProperty($property)
    {
        $this->property = $property;
    }

    public function getParameters()
    {
        return $this->parameters;
    }

    public function setParameters($parameters)
    {
        $this->parameters = $parameters;
    }

    public function getPriority()
    {
        return $this->priority;
    }

    public function setPriority($priority)
    {
        $this->priority = $priority;
    }

    public function getRouteName()
    {
        return $this->routeName;
    }

    public function setRouteName($routeName)
    {
        $this->routeName = $routeName;
    }

    public function getRoute()
    {
        return $this->route;
    }

    public function setRoute($route)
    {
        $this->route = $route;
    }

    public function getIf()
    {
        return $this->if;
    }

    public function setIf($if)
    {
        $this->if = $if;
    }

    public function getTags()
    {
        return $this->tags;
    }

    public function setTags($tags)
    {
        $this->tags = $tags;
    }
}
