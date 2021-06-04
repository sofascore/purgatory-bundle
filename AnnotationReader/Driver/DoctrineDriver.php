<?php

namespace SofaScore\CacheRefreshBundle\AnnotationReader\Driver;

use SofaScore\CacheRefreshBundle\AnnotationReader\DriverInterface;

class DoctrineDriver implements DriverInterface
{
    /**
     * @var \Doctrine\Common\Annotations\Reader
     */
    protected $reader;

    /**
     * @param \Doctrine\Common\Annotations\Reader $reader
     *
     * @throws \Exception
     */
    public function __construct($reader)
    {
        if (!interface_exists('Doctrine\\Common\\Annotations\\Reader')) {
            throw new \Exception("Interface '\\Doctrine\\Common\\Annotations\\Reader' does not exist.");
        }

        if (!($reader instanceof \Doctrine\Common\Annotations\Reader)) {
            throw new \Exception("Doctrine Reader must be instance of '\\Doctrine\\Common\\Annotations\\Reader'");
        }

        $this->reader = $reader;
    }

    /**
     * Gets the annotations applied to a class.
     *
     * @param \ReflectionClass $class the ReflectionClass of the class from which
     *                                the class annotations should be read
     *
     * @return array an array of Annotations
     */
    public function getClassAnnotations(\ReflectionClass $class)
    {
        return $this->reader->getClassAnnotations($class);
    }

    /**
     * Gets a class annotation.
     *
     * @param \ReflectionClass $class          the ReflectionClass of the class from which
     *                                         the class annotations should be read
     * @param class-string $annotationName the name of the annotation
     *
     * @return object|null the Annotation or NULL, if the requested annotation does not exist
     */
    public function getClassAnnotation(\ReflectionClass $class, string $annotationName)
    {
        return $this->reader->getClassAnnotation($class, $annotationName);
    }

    /**
     * Gets the annotations applied to a method.
     *
     * @param \ReflectionMethod $method the ReflectionMethod of the method from which
     *                                  the annotations should be read
     *
     * @return array an array of Annotations
     */
    public function getMethodAnnotations(\ReflectionMethod $method)
    {
        return $this->reader->getMethodAnnotations($method);
    }

    /**
     * Gets a method annotation.
     *
     * @param \ReflectionMethod $method         the ReflectionMethod to read the annotations from
     * @param class-string $annotationName the name of the annotation
     *
     * @return object|null the Annotation or NULL, if the requested annotation does not exist
     */
    public function getMethodAnnotation(\ReflectionMethod $method, string $annotationName)
    {
        return $this->reader->getMethodAnnotation($method, $annotationName);
    }

    /**
     * Gets the annotations applied to a property.
     *
     * @param \ReflectionProperty $property the ReflectionProperty of the property
     *                                      from which the annotations should be read
     *
     * @return array an array of Annotations
     */
    public function getPropertyAnnotations(\ReflectionProperty $property)
    {
        return $this->reader->getPropertyAnnotations($property);
    }

    /**
     * Gets a property annotation.
     *
     * @param \ReflectionProperty $property       the ReflectionProperty to read the annotations from
     * @param class-string $annotationName the name of the annotation
     *
     * @return object|null the Annotation or NULL, if the requested annotation does not exist
     */
    public function getPropertyAnnotation(\ReflectionProperty $property, string $annotationName)
    {
        return $this->reader->getPropertyAnnotation($property, $annotationName);
    }
}
