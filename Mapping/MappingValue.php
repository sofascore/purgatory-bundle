<?php

namespace SofaScore\CacheRefreshBundle\Mapping;

class MappingValue
{
    protected string $routeName;
    protected array $parameters = [];
    protected ?string $priority = null;
    protected ?string $if = null;
    protected array $tags = [];

    public function __construct(string $routeName)
    {
        $this->routeName = $routeName;
    }

    public static function __set_state(array $values): self
    {
        $object = new MappingValue($values['routeName']);
        unset($values['routeName']);

        // set object properties
        foreach ($values as $key => $value) {
            if (property_exists($object, $key)) {
                $object->{$key} = $value;
            }
        }

        return $object;
    }

    public function getRouteName(): string
    {
        return $this->routeName;
    }

    public function setRouteName(string $routeName): void
    {
        $this->routeName = $routeName;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function setParameters(array $parameters): void
    {
        $this->parameters = $parameters;
    }

    public function getPriority(): ?string
    {
        return $this->priority;
    }

    public function setPriority(?string $priority): void
    {
        $this->priority = $priority;
    }

    public function getIf(): ?string
    {
        return $this->if;
    }

    public function setIf(?string $if): void
    {
        $this->if = $if;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function setTags(array $tags): void
    {
        $this->tags = $tags;
    }
}
