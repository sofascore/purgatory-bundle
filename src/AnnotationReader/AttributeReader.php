<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\AnnotationReader;

use Doctrine\Common\Annotations\Reader;

class AttributeReader implements Reader
{
    public function getClassAnnotations(\ReflectionClass $class): array
    {
        $reflectionAttributes = $class->getAttributes();
        $attributes = [];
        foreach ($reflectionAttributes as $ref) {
            $attribute = $ref->newInstance();
            $attributes[] = $attribute;
        }

        return $attributes;
    }

    public function getClassAnnotation(\ReflectionClass $class, $annotationName): ?object
    {
        $attributes = $class->getAttributes($annotationName, \ReflectionAttribute::IS_INSTANCEOF);
        if (isset($attributes[0])) {
            return $attributes[0]->newInstance();
        }

        return null;
    }

    public function getMethodAnnotations(\ReflectionMethod $method): array
    {
        $reflectionAttributes = $method->getAttributes();
        $attributes = [];
        foreach ($reflectionAttributes as $ref) {
            $attributes[] = $ref->newInstance();
        }

        return $attributes;
    }

    public function getMethodAnnotation(\ReflectionMethod $method, $annotationName): ?object
    {
        $attributes = $method->getAttributes($annotationName, \ReflectionAttribute::IS_INSTANCEOF);
        if (isset($attributes[0])) {
            return $attributes[0]->newInstance();
        }

        return null;
    }

    public function getPropertyAnnotations(\ReflectionProperty $property): array
    {
        $reflectionAttributes = $property->getAttributes();
        $attributes = [];
        foreach ($reflectionAttributes as $ref) {
            $attributes[] = $ref->newInstance();
        }

        return $attributes;
    }

    public function getPropertyAnnotation(\ReflectionProperty $property, $annotationName): ?object
    {
        $attributes = $property->getAttributes($annotationName, \ReflectionAttribute::IS_INSTANCEOF);
        if (isset($attributes[0])) {
            return $attributes[0]->newInstance();
        }

        return null;
    }
}
