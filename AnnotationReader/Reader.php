<?php

namespace SofaScore\Purgatory\AnnotationReader;

class Reader
{
    protected DriverInterface $driver;

    public function __construct(DriverInterface $driver)
    {
        $this->driver = $driver;
    }

    /**
     * @param \ReflectionClass|\ReflectionMethod|\ReflectionProperty $item
     *
     * @throws ReaderException|\ReflectionException
     */
    public function getAnnotations($item): array
    {
        return $this->getItemAnnotationsDeep($item);
    }

    /**
     * @param \ReflectionClass|\ReflectionMethod|\ReflectionProperty $item
     *
     * @throws \SofaScore\Purgatory\AnnotationReader\ReaderException
     * @throws \ReflectionException
     */
    protected function getItemAnnotationsDeep($item): array
    {
        $annotations = [];
        $class = $this->getItemClass($item);

        while (false !== $class) {
            $lookupItem = $this->getAnnotationLookupItem($item, $class);
            // if parent class does not have the item, means we've reached the top
            if (null === $lookupItem) {
                break;
            }

            // if item is defined in parent class, lookupItem will be found but it's class will be set to
            // that of the parent, which causes annotations to double. Therefore annotations are
            // fetched only when lookupItem's class matches currently iterated class in hierarchy.

            if ($this->getItemClass($lookupItem) === $class) {
                $itemAnnotations = $this->getItemAnnotations($lookupItem);
                $itemAnnotations = $this->groupAnnotationsByClass($itemAnnotations);
                // add newly found to annotations array
                $annotations = array_replace($itemAnnotations, $annotations);
            }

            $class = get_parent_class($class);
        }

        return $annotations;
    }

    public function groupAnnotationsByClass(array $annotations): array
    {
        $groupedAnnotations = [];

        foreach ($annotations as $annotation) {
            $annotationClass = get_class($annotation);

            if (!isset($groupedAnnotations[$annotationClass])) {
                $groupedAnnotations[$annotationClass] = [];
            }

            $groupedAnnotations[$annotationClass][] = $annotation;
        }

        return $groupedAnnotations;
    }

    /**
     * @return \ReflectionClass|\ReflectionMethod|\ReflectionProperty|null
     *
     * @throws ReaderException
     * @throws \ReflectionException
     */
    public function getAnnotationLookupItem($item, $class): \ReflectionClass|\ReflectionMethod|\ReflectionProperty|null
    {
        if ($item instanceof \ReflectionClass) {
            return class_exists($class) ? new \ReflectionClass($class) : null;
        }

        if ($item instanceof \ReflectionMethod) {
            return method_exists($class, $item->getName()) ?
                new \ReflectionMethod($class, $item->getName()) : null;
        }

        if ($item instanceof \ReflectionProperty) {
            return property_exists($class, $item->getName()) ?
                new \ReflectionProperty($class, $item->getName()) : null;
        }

        throw new ReaderException('Unsupported type.', $item);
    }

    /**
     * @param \ReflectionClass|\ReflectionMethod|\ReflectionProperty $item
     *
     * @throws ReaderException
     */
    public function getItemAnnotations($item): array
    {
        if ($item instanceof \ReflectionClass) {
            return $this->driver->getClassAnnotations($item);
        }

        if ($item instanceof \ReflectionMethod) {
            return $this->driver->getMethodAnnotations($item);
        }

        if ($item instanceof \ReflectionProperty) {
            return $this->driver->getPropertyAnnotations($item);
        }

        throw new ReaderException('Unsupported type.', $item);
    }

    /**
     * @param \ReflectionClass|\ReflectionMethod|\ReflectionProperty $item
     *
     * @throws ReaderException
     */
    public function getItemClass($item): string
    {
        if ($item instanceof \ReflectionClass) {
            return $item->getName();
        }

        if ($item instanceof \ReflectionMethod) {
            return $item->class;
        }

        if ($item instanceof \ReflectionProperty) {
            return $item->class;
        }

        throw new ReaderException('Unsupported type.', $item);
    }
}
