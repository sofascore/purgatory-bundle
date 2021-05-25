<?php

namespace SofaScore\CacheRefreshBundle\Annotation;

/**
 * @Annotation
 * @Target("METHOD")
 */
final class Properties
{
    /**
     * @var array
     */
    private $properties = [];

    /**
     * @param array $values
     */
    public function __construct($values)
    {
        $this->properties = (array) $values['value'];
    }

    /**
     * @return array
     */
    public function getProperties()
    {
        return $this->properties;
    }
}
