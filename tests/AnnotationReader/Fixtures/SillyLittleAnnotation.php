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

    public function __construct(array|string $value = null)
    {
        $this->value = \is_array($value) ? ($value['value'] ?? null) : $value;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }
}
