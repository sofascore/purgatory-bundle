<?php

namespace SofaScore\Purgatory\Annotation;

/**
 * @Annotation
 * @Target("METHOD")
 * @Repeatable
 */
#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_METHOD)]
final class SubscribeTo
{
    private string $object;
    private ?array $parameters = null;
    private ?array $properties = null;
    private ?string $if;
    private ?array $tags;

    /**
     * @param array|string $value
     */
    public function __construct(/* array|string */$value = [], ?array $parameters = null, ?array $properties = null, ?string $if = null, ?array $tags = [])
    {
        if (is_string($value)) {
            $object = $value;
        } elseif (is_array($value)) {
            $object = $value['value'];
        } else {
            throw new \TypeError(sprintf('"%s": Argument $value is expected to be a string or array, got "%s".', __METHOD__, get_debug_type($value)));
        }

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
        $this->parameters = $value['parameters'] ?? $parameters;

        // set properties if defined (overriding one from 'value')
        $this->properties = $value['properties'] ?? $properties ?? $this->properties;

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
