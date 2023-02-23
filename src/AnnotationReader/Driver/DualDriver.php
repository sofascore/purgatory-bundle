<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\AnnotationReader\Driver;

use Doctrine\Common\Annotations\Reader;
use Sofascore\PurgatoryBundle\AnnotationReader\DriverInterface;

class DualDriver implements DriverInterface
{
    private Reader $annotationReader;
    private Reader $attributeReader;

    public function __construct(Reader $annotationReader, Reader $attributeReader)
    {
        $this->annotationReader = $annotationReader;
        $this->attributeReader = $attributeReader;
    }

    public function getClassAnnotations(\ReflectionClass $class): array
    {
        $annotations = $this->annotationReader->getClassAnnotations($class);

        return array_merge($annotations, $this->attributeReader->getClassAnnotations($class));
    }

    public function getClassAnnotation(\ReflectionClass $class, string $annotationName): ?object
    {
        $annotation = $this->annotationReader->getClassAnnotation($class, $annotationName);
        if (null !== $annotation) {
            return $annotation;
        }

        return $this->attributeReader->getClassAnnotation($class, $annotationName);
    }

    public function getMethodAnnotations(\ReflectionMethod $method): array
    {
        $annotations = $this->annotationReader->getMethodAnnotations($method);

        return array_merge($annotations, $this->attributeReader->getMethodAnnotations($method));
    }

    public function getMethodAnnotation(\ReflectionMethod $method, string $annotationName): ?object
    {
        $annotation = $this->annotationReader->getMethodAnnotation($method, $annotationName);
        if (null !== $annotation) {
            return $annotation;
        }

        return $this->attributeReader->getMethodAnnotation($method, $annotationName);
    }

    public function getPropertyAnnotations(\ReflectionProperty $property): array
    {
        $annotations = $this->annotationReader->getPropertyAnnotations($property);

        return array_merge($annotations, $this->attributeReader->getPropertyAnnotations($property));
    }

    public function getPropertyAnnotation(\ReflectionProperty $property, string $annotationName): ?object
    {
        $annotation = $this->annotationReader->getPropertyAnnotation($property, $annotationName);
        if (null !== $annotation) {
            return $annotation;
        }

        return $this->attributeReader->getPropertyAnnotation($property, $annotationName);
    }
}
