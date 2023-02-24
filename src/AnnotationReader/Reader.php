<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\AnnotationReader;

class Reader
{
    protected DriverInterface $driver;

    public function __construct(DriverInterface $driver)
    {
        $this->driver = $driver;
    }

    /**
     * @throws ReaderException|\ReflectionException
     */
    public function getAnnotations(\ReflectionMethod|\ReflectionClass|\ReflectionProperty $item): array
    {
        return $this->getItemAnnotationsDeep($item);
    }

    /**
     * @throws \Sofascore\PurgatoryBundle\AnnotationReader\ReaderException
     * @throws \ReflectionException
     */
    protected function getItemAnnotationsDeep(\ReflectionMethod|\ReflectionClass|\ReflectionProperty $item): array
    {
        $annotations = [];

        /** @var class-string $class */
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
            $annotationClass = $annotation::class;

            if (!isset($groupedAnnotations[$annotationClass])) {
                $groupedAnnotations[$annotationClass] = [];
            }

            $groupedAnnotations[$annotationClass][] = $annotation;
        }

        return $groupedAnnotations;
    }

    /**
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
     * @throws ReaderException
     */
    public function getItemAnnotations(\ReflectionMethod|\ReflectionClass|\ReflectionProperty $item): array
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
     * @throws ReaderException
     */
    public function getItemClass(\ReflectionMethod|\ReflectionClass|\ReflectionProperty $item): string
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
