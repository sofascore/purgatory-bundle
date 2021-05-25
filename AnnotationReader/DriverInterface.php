<?php

namespace SofaScore\CacheRefreshBundle\AnnotationReader;

interface DriverInterface
{
    /**
     * Gets the annotations applied to a class.
     *
     * @param \ReflectionClass $class the ReflectionClass of the class from which
     *                                the class annotations should be read
     *
     * @return array an array of Annotations
     */
    public function getClassAnnotations(\ReflectionClass $class);

    /**
     * Gets a class annotation.
     *
     * @param \ReflectionClass $class          the ReflectionClass of the class from which
     *                                         the class annotations should be read
     * @param string           $annotationName the name of the annotation
     *
     * @return object|null the Annotation or NULL, if the requested annotation does not exist
     */
    public function getClassAnnotation(\ReflectionClass $class, $annotationName);

    /**
     * Gets the annotations applied to a method.
     *
     * @param \ReflectionMethod $method the ReflectionMethod of the method from which
     *                                  the annotations should be read
     *
     * @return array an array of Annotations
     */
    public function getMethodAnnotations(\ReflectionMethod $method);

    /**
     * Gets a method annotation.
     *
     * @param \ReflectionMethod $method         the ReflectionMethod to read the annotations from
     * @param string            $annotationName the name of the annotation
     *
     * @return object|null the Annotation or NULL, if the requested annotation does not exist
     */
    public function getMethodAnnotation(\ReflectionMethod $method, $annotationName);

    /**
     * Gets the annotations applied to a property.
     *
     * @param \ReflectionProperty $property the ReflectionProperty of the property
     *                                      from which the annotations should be read
     *
     * @return array an array of Annotations
     */
    public function getPropertyAnnotations(\ReflectionProperty $property);

    /**
     * Gets a property annotation.
     *
     * @param \ReflectionProperty $property       the ReflectionProperty to read the annotations from
     * @param string              $annotationName the name of the annotation
     *
     * @return object|null the Annotation or NULL, if the requested annotation does not exist
     */
    public function getPropertyAnnotation(\ReflectionProperty $property, $annotationName);
}
