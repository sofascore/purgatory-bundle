<?php

namespace SofaScore\CacheRefreshBundle\Mapping;

class MappingValue
{
    /**
     * @var string
     */
    protected $routeName;

    /**
     * @var array
     */
    protected $parameters;

    /**
     * @var string|null
     */
    protected $priority;

    /**
     * @var string
     */
    protected $if;

    /**
     * @var array
     */
    protected $tags;

    /**
     * @param string $routeName
     */
    public function __construct($routeName)
    {
        $this->routeName = $routeName;
    }

    /**
     * @param $values
     *
     * @return MappingValue
     */
    public static function __set_state($values)
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

    public function getRouteName()
    {
        return $this->routeName;
    }

    public function setRouteName($routeName)
    {
        $this->routeName = $routeName;
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
