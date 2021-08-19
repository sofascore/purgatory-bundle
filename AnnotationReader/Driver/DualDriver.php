<?php

namespace SofaScore\Purgatory\AnnotationReader\Driver;

use Doctrine\Common\Annotations\Reader;
use SofaScore\Purgatory\AnnotationReader\DriverInterface;

class DualDriver implements DriverInterface
{
    private Reader $annotationReader;
    private Reader $attributeReader;

    protected $isPHP8;

    public function __construct(Reader $annotationReader, Reader $attributeReader)
    {
        $this->annotationReader = $annotationReader;
        $this->attributeReader = $attributeReader;
        $this->isPHP8 = PHP_VERSION_ID >= 80000;
    }

    public function getClassAnnotations(\ReflectionClass $class): array
    {
        $annotations = $this->annotationReader->getClassAnnotations($class);
        if (!$this->isPHP8) {
            return $annotations;
        }

        return array_merge($annotations, $this->attributeReader->getClassAnnotations($class));
    }

    public function getClassAnnotation(\ReflectionClass $class, string $annotationName): ?object
    {
        $annotation = $this->annotationReader->getClassAnnotation($class, $annotationName);
        if (null !== $annotation) {
            return $annotation;
        }

        if ($this->isPHP8) {
            return $this->attributeReader->getClassAnnotation($class, $annotationName);
        }

        return null;
    }

    public function getMethodAnnotations(\ReflectionMethod $method): array
    {
        $annotations = $this->annotationReader->getMethodAnnotations($method);
        if (!$this->isPHP8) {
            return $annotations;
        }

        return array_merge($annotations, $this->attributeReader->getMethodAnnotations($method));
    }

    public function getMethodAnnotation(\ReflectionMethod $method, string $annotationName): ?object
    {
        $annotation = $this->annotationReader->getMethodAnnotation($method, $annotationName);
        if (null !== $annotation) {
            return $annotation;
        }

        if ($this->isPHP8) {
            return $this->attributeReader->getMethodAnnotation($method, $annotationName);
        }

        return null;
    }

    public function getPropertyAnnotations(\ReflectionProperty $property): array
    {
        $annotations = $this->annotationReader->getPropertyAnnotations($property);
        if (!$this->isPHP8) {
            return $annotations;
        }

        return array_merge($annotations, $this->attributeReader->getPropertyAnnotations($property));
    }

    public function getPropertyAnnotation(\ReflectionProperty $property, string $annotationName): ?object
    {
        $annotation = $this->annotationReader->getPropertyAnnotation($property, $annotationName);
        if (null !== $annotation) {
            return $annotation;
        }

        if ($this->isPHP8) {
            return $this->attributeReader->getPropertyAnnotation($property, $annotationName);
        }

        return null;
    }
}
