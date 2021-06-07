<?php

namespace SofaScore\CacheRefreshBundle\Annotation;

/**
 * @Annotation
 * @Target("METHOD")
 * @Repeatable
 */
final class SubscribeTo
{
    private string $object;
    private ?array $parameters = null;
    private ?array $properties = null;
    private ?string $priority;
    private ?string $if;
    private ?array $routes = null;
    private ?array $tags;

    public function __construct(array $values)
    {
        $object = $values['value'];

        // if property is defined
        if (str_contains($object, '.')) {
            // split value to object and property
            [$object, $property] = explode('.', $object, 2);

            // add property to properties list
            $this->properties = [$property];
        }

        // set object class
        $this->object = $object;

        // set parameters if defined
        if (isset($values['parameters'])) {
            $this->parameters = (array) $values['parameters'];
        }

        // set properties if defined (overriding one from 'value')
        if (isset($values['properties'])) {
            $this->properties = (array) $values['properties'];
        }

        // set priority if defined
        $this->priority = $values['priority'] ?? null;

        // set 'if' condition
        $this->if = $values['if'] ?? null;

        // set routes
        if (isset($values['routes'])) {
            $this->routes = (array) $values['routes'];
        }

        // set tags
        $this->tags = $values['tags'] ?? [];
    }

    public function getObject(): string
    {
        return $this->object;
    }

    public function getParameters(): ?array
    {
        return $this->parameters;
    }

    public function getProperties(): ?array
    {
        return $this->properties;
    }

    public function getPriority(): ?string
    {
        return $this->priority;
    }

    public function getIf(): ?string
    {
        return $this->if;
    }

    public function getRoutes(): ?array
    {
        return $this->routes;
    }

    public function setRoutes(?array $routes): void
    {
        $this->routes = $routes;
    }

    public function getTags(): ?array
    {
        return $this->tags;
    }
}
