<?php

namespace SofaScore\Purgatory\Annotation;

/**
 * @Annotation
 * @Target("METHOD")
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class Properties
{
    private array $properties;

    public function __construct(array $value = [])
    {
        $this->properties = $value['value'] ?? $value;
    }

    public function getProperties(): array
    {
        return $this->properties;
    }
}
