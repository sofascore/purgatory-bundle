<?php

namespace SofaScore\CacheRefreshBundle\Mapping;

use Traversable;

class MappingCollection implements \IteratorAggregate, \Countable
{
    /**
     * @var array<MappingValue[]>
     */
    protected $mappings = [];

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Retrieve an external iterator.
     *
     * @see http://php.net/manual/en/iteratoraggregate.getiterator.php
     *
     * @return Traversable An instance of an object implementing <b>Iterator</b> or
     *                     <b>Traversable</b>
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->mappings);
    }

    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * Count elements of an object.
     *
     * @see http://php.net/manual/en/countable.count.php
     *
     * @return int The custom count as an integer.
     *             </p>
     *             <p>
     *             The return value is cast to an integer.
     */
    public function count()
    {
        return count($this->mappings);
    }

    /**
     * Adds a mapping value.
     *
     * @param string       $name         The mapping name
     * @param MappingValue $mappingValue A MappingValue instance
     */
    public function add($name, MappingValue $mappingValue)
    {
        if (!isset($this->mappings[$name])) {
            $this->mappings[$name] = [];
        }

        $this->mappings[$name][] = $mappingValue;
    }

    /**
     * Returns all mappings in this collection.
     *
     * @return MappingValue[] An array of mappings
     */
    public function all()
    {
        return $this->mappings;
    }

    /**
     * Gets a mapping values by name.
     *
     * @param string $name The mapping name
     *
     * @return MappingValue[]|null A MappingValue instance or null when not found
     */
    public function get($name)
    {
        return $this->mappings[$name] ?? null;
    }

    /**
     * Removes a mapping or an array of mappings by name from the collection.
     *
     * @param string|array $name The mapping name or an array of mapping names
     */
    public function remove($name)
    {
        foreach ((array) $name as $n) {
            unset($this->mappings[$n]);
        }
    }

    /**
     * @param $values
     *
     * @return MappingCollection
     */
    public static function __set_state($values)
    {
        $collection = new MappingCollection();
        $collection->mappings = $values['mappings'];

        return $collection;
    }
}
