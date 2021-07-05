<?php

namespace SofaScore\Purgatory\Annotation;

/**
 * @Annotation
 * @Target("METHOD")
 */
final class Properties
{
    private array $properties;

    public function __construct(array $values)
    {
        $this->properties = (array) ($values['value'] ?? []);
    }

    public function getProperties(): array
    {
        return $this->properties;
    }
}
