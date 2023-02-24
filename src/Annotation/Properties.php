<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Annotation;

/**
 * @Annotation
 *
 * @Target("METHOD")
 *
 * @codeCoverageIgnore
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class Properties
{
    private array $properties;

    public function __construct(array|string $value = [])
    {
        $this->properties = $value['value'] ?? $value;
    }

    public function getProperties(): array
    {
        return $this->properties;
    }
}
