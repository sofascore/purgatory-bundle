<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\AnnotationReader\Fixtures;

/**
 * @SillyLittleAnnotation("annotation")
 */
#[SillyLittleAnnotation('attribute')]
class DualClass
{
    /**
     * @SillyLittleAnnotation("annotation")
     */
    #[SillyLittleAnnotation('attribute')]
    private string $propertyWithBothAnnotationAndAttribute;

    /**
     * @SillyLittleAnnotation("annotation")
     */
    private string $propertyWithAnnotation;

    #[SillyLittleAnnotation('attribute')]
    private string $propertyWithAttribute;

    private string $propertyWithNoAnnotations;

    /**
     * @SillyLittleAnnotation("annotation")
     */
    #[SillyLittleAnnotation('attribute')]
    public function methodWithBothAnnotationAndAttribute(): void
    {
    }

    /**
     * @SillyLittleAnnotation("annotation")
     */
    public function methodWithAnnotation(): void
    {
    }

    #[SillyLittleAnnotation('attribute')]
    public function methodWithAttribute(): void
    {
    }

    public function methodWithNoAnnotations(): void
    {
    }
}
