<?php

namespace SofaScore\Purgatory\Mapping;

class MappingCollection implements \IteratorAggregate, \Countable
{
    /**
     * @var array<MappingValue[]>
     */
    protected array $mappings = [];

    /**
     * {@inheritdoc}
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->mappings);
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return count($this->mappings);
    }

    /**
     * Adds a mapping value.
     *
     * @param string       $name         The mapping name
     * @param MappingValue $mappingValue A MappingValue instance
     */
    public function add(string $name, MappingValue $mappingValue): void
    {
        if (!isset($this->mappings[$name])) {
            $this->mappings[$name] = [];
        }

        $this->mappings[$name][] = $mappingValue;
    }

    /**
     * Returns all mappings in this collection.
     *
     * @return array<MappingValue[]> An array of mappings
     */
    public function all(): array
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
    public function get(string $name): ?array
    {
        return $this->mappings[$name] ?? null;
    }

    /**
     * Removes a mapping or an array of mappings by name from the collection.
     *
     * @param string|array $name The mapping name or an array of mapping names
     */
    public function remove($name): void
    {
        foreach ((array) $name as $n) {
            unset($this->mappings[$n]);
        }
    }

    public static function __set_state(array $values): self
    {
        $collection = new self();
        $collection->mappings = $values['mappings'] ?? [];

        return $collection;
    }
}
