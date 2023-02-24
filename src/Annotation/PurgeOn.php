<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Annotation;

/**
 * @Annotation
 *
 * @Target("METHOD")
 *
 * @Repeatable
 *
 * @codeCoverageIgnore
 */
#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_METHOD)]
final class PurgeOn
{
    private string $object;
    private ?array $parameters;
    private ?array $properties;
    private ?string $if;
    private ?array $tags;

    public function __construct(array|string $value = [], ?array $properties = null, ?array $parameters = null, ?string $if = null, ?array $tags = [])
    {
        // set object class
        $this->object = \is_array($value) ? $value['value'] : $value;

        // set parameters if defined
        $this->parameters = $value['parameters'] ?? $parameters;

        // set properties if defined
        $this->properties = $value['properties'] ?? $properties;

        // set 'if' condition
        $this->if = $value['if'] ?? $if;

        // set tags
        $this->tags = $value['tags'] ?? $tags ?? [];
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

    public function getIf(): ?string
    {
        return $this->if;
    }

    public function getTags(): ?array
    {
        return $this->tags;
    }
}
