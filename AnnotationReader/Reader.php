<?php

namespace SofaScore\Purgatory\AnnotationReader;

class Reader
{
    /**
     * @var DriverInterface
     */
    protected $driver;

    public function __construct(DriverInterface $driver)
    {
        $this->driver = $driver;
    }

    /**
     * @param \ReflectionClass|\ReflectionMethod|\ReflectionProperty $item
     *
     * @return array
     *
     * @throws ReaderException
     */
    public function getAnnotations($item)
    {
        return $this->getItemAnnotationsDeep($item);
    }

    /**
     * @param \ReflectionClass|\ReflectionMethod|\ReflectionProperty $item
     *
     * @return array
     *
     * @throws ReaderException
     */
    protected function getItemAnnotationsDeep($item)
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
            // fethched only when lookupItem's class matches currently itterated class in hierarchy.

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

    /**
     * @param $annotations
     *
     * @return array<array>
     */
    public function groupAnnotationsByClass($annotations)
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
     * @param $item
     * @param $class
     *
     * @return \ReflectionClass|\ReflectionMethod|\ReflectionProperty|null
     *
     * @throws ReaderException
     */
    public function getAnnotationLookupItem($item, $class)
    {
        $lookupItem = null;

        if ($item instanceof \ReflectionClass) {
            $lookupItem = class_exists($class) ? new \ReflectionClass($class) : $lookupItem;
        } elseif ($item instanceof \ReflectionMethod) {
            $lookupItem = method_exists($class, $item->getName()) ?
                new \ReflectionMethod($class, $item->getName()) : $lookupItem;
        } elseif ($item instanceof \ReflectionProperty) {
            $lookupItem = property_exists($class, $item->getName()) ?
                new \ReflectionProperty($class, $item->getName()) : $lookupItem;
        } else {
            throw new ReaderException('Unsupported type.', $item);
        }

        return $lookupItem;
    }

    /**
     * @param \ReflectionClass|\ReflectionMethod|\ReflectionProperty $item
     *
     * @return array
     *
     * @throws ReaderException
     */
    public function getItemAnnotations($item)
    {
        if ($item instanceof \ReflectionClass) {
            $annotations = $this->driver->getClassAnnotations($item);
        } elseif ($item instanceof \ReflectionMethod) {
            $annotations = $this->driver->getMethodAnnotations($item);
        } elseif ($item instanceof \ReflectionProperty) {
            $annotations = $this->driver->getPropertyAnnotations($item);
        } else {
            throw new ReaderException('Unsupported type.', $item);
        }

        return $annotations;
    }

    /**
     * @param \ReflectionClass|\ReflectionMethod|\ReflectionProperty $item
     *
     * @return string
     *
     * @throws ReaderException
     */
    public function getItemClass($item)
    {
        if ($item instanceof \ReflectionClass) {
            $class = $item->getName();
        } elseif ($item instanceof \ReflectionMethod) {
            $class = $item->class;
        } elseif ($item instanceof \ReflectionProperty) {
            $class = $item->class;
        } else {
            throw new ReaderException('Unsupported type.', $item);
        }

        return $class;
    }
}
