<?php

namespace SofaScore\Purgatory\Annotation;

/**
 * @Annotation
 * @Target("METHOD")
 */
final class SubscribeTo
{
    /**
     * @var mixed
     */
    private $object;

    /**
     * @var array
     */
    private $parameters;

    /**
     * @var
     */
    private $properties;

    /**
     * @var string
     */
    private $priority;

    /**
     * @var string
     */
    private $if;

    /**
     * @var array|null
     */
    private $routes;

    /**
     * @var array
     */
    private $tags;

    public function __construct(array $values)
    {
        $object = $values['value'];

        // if property is defined
        if (false !== strpos($object, '.')) {
            // split value to object and property
            [$object, $property] = explode('.', $object, 2);

            // add property to properties list
            $this->properties = [$property];
        }

        // set object class
        $this->object = $object;

        // set parameters if defined
        if (isset($values['parameters'])) {
            $this->parameters = $values['parameters'];
        }

        // set properties if defined (overriding one from 'value')
        if (isset($values['properties'])) {
            $this->properties = (array) $values['properties'];
        }

        // set priority if defined
        if (isset($values['priority'])) {
            $this->priority = $values['priority'];
        }

        // set 'if' condition
        if (isset($values['if'])) {
            $this->if = $values['if'];
        }

        // set routes
        if (isset($values['routes'])) {
            $this->routes = (array) $values['routes'];
        }

        // set properties default
        if (null === $this->properties) {
            $this->properties = [];
        }

        // set tags
        $this->tags = $values['tags'] ?? [];
    }

    public function getObject()
    {
        return $this->object;
    }

    public function getParameters()
    {
        return $this->parameters;
    }

    public function getProperties()
    {
        return $this->properties;
    }

    public function getPriority()
    {
        return $this->priority;
    }

    public function getIf()
    {
        return $this->if;
    }

    public function getRoutes()
    {
        return $this->routes;
    }

    public function setRoutes($routes)
    {
        $this->routes = $routes;
    }

    public function getTags()
    {
        return $this->tags;
    }
}
