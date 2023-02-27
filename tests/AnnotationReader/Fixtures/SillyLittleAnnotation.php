<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\AnnotationReader\Fixtures;

/**
 * @Annotation
 */
#[\Attribute(\Attribute::TARGET_ALL)]
class SillyLittleAnnotation
{
    private ?string $value;

    public function __construct($value = null)
    {
        if (is_array($value)) {
            $this->value = $value['value'] ?? null;
        } elseif (is_string($value)) {
            $this->value = $value;
        } else {
            throw new \TypeError('Expected string or array');
        }
    }

    public function getValue(): ?string
    {
        return $this->value;
    }
}
