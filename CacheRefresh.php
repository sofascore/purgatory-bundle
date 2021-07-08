<?php

namespace SofaScore\Purgatory;

use SofaScore\Purgatory\Mapping\Loader\LoaderInterface;
use SofaScore\Purgatory\Mapping\MappingCollection;
use SofaScore\Purgatory\Mapping\MappingValue;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

class CacheRefresh
{
    public const PRIORITY_DEFAULT = '0';
    public const ROUTE_TAG = 'route';

    /**
     * @var MappingCollection
     */
    protected $mappings;

    /**
     * @var LoaderInterface
     */
    protected $mappingsLoader;

    /**
     * @var PropertyAccessorInterface
     */
    protected $propertyAccessor;

    /**
     * @var ExpressionLanguage
     */
    protected $expressionLanguage;

    public function __construct(LoaderInterface $mappingsLoader, PropertyAccessorInterface $propertyAccessor)
    {
        $this->mappingsLoader = $mappingsLoader;
        $this->propertyAccessor = $propertyAccessor;

        if (!class_exists('Symfony\Component\ExpressionLanguage\ExpressionLanguage')) {
            throw new \RuntimeException('Unable to use expressions as the Symfony ExpressionLanguage component is not installed.');
        }

        $this->expressionLanguage = new ExpressionLanguage();
    }

    /**
     * @return MappingCollection
     */
    public function getMappings()
    {
        if (null === $this->mappings) {
            $this->mappings = $this->mappingsLoader->load();
        }

        return $this->mappings;
    }

    /**
     * Returns array of url definitions:
     * [
     *      [ route => 'route_name', params => ['parama1' => 'param_value', ... ], priority => 'priority' ],
     *      ...
     * ].
     *
     * @param mixed $object            Object that was changed
     * @param array $changedProperties List of property paths (ex. ['status.description', 'userCount', ...])
     *
     * @return array
     */
    public function getUrlsToRefresh($object, $changedProperties)
    {
        // check if there are chages
        if (count($changedProperties) <= 0) {
            return [];
        }

        // fetch mappings
        $mappings = $this->getMappings();

        // set data
        $stack = [];
        $urls = [];

        // init stack
        $stack[] = [$this->getObjectClass($object), array_shift($changedProperties)];

        // process class properties
        while (count($stack) > 0) {
            // get class and property
            [$class, $property] = array_pop($stack);

            // set key
            $mappingKey = $class.'::'.$property;

            // process class
            if (null !== $mappingValues = $mappings->get($class)) {
                // process and continue
                $this->processMappingValues($object, $mappingValues, $urls);
            }

            // if mappings defined
            if (null !== $mappingValues = $mappings->get($mappingKey)) {
                // process values
                $this->processMappingValues($object, $mappingValues, $urls);
            }
            // check if class has parent
            if (false !== $parent = $this->getParentClass($class)) {
                // add parent class and current property to stack to be checked
                $stack[] = [$parent, $property];
            }
            // if is subproperty
            if (false !== $dotPosition = strrpos($property, '.')) {
                // strip last property path
                $newProperty = substr($property, 0, $dotPosition);

                // add property with current class to be checked
                $stack[] = [$this->getObjectClass($object), $newProperty];
            }

            // move to next porperty if it exists
            if (count($changedProperties) > 0) {
                $stack[] = [$this->getObjectClass($object), array_shift($changedProperties)];
            }
        }

        return $urls;
    }

    /**
     * @param mixed          $object
     * @param MappingValue[] $mappingValues
     */
    public function processMappingValues($object, array $mappingValues, array &$urls)
    {
        foreach ($mappingValues as $mappingValue) {
            $routeName = $mappingValue->getRouteName();
            $routeParameters = [];

            // process mapping 'if' condition
            if (null !== $condition = $mappingValue->getIf()) {
                $conditionResult = $this->expressionLanguage->evaluate($condition, ['obj' => $object]);

                // if 'if' condition evaluates to false, skip this mapping
                if (false === $conditionResult) {
                    continue;
                }
            }

            // set route parameters
            foreach ($mappingValue->getParameters() ?? [] as $param => $paramProperties) {
                $routeParameters[$param] = [];

                foreach ($paramProperties as $property) {
                    // if property is fixed string, just set parameter and continue
                    if ('@' === substr($property, 0, 1)) {
                        $routeParameters[$param][] = substr($property, 1);
                        continue;
                    }

                    // otherwise property is calls property so fetch it
                    $propertyParts = explode('|', $property);
                    $property = $propertyParts[0];
                    $propertyDefault = $propertyParts[1] ?? null;

                    try {
                        $propertyValue = $this->propertyAccessor->getValue($object, $property);

                        // if property value is an array then generate param for every element
                        if (is_array($propertyValue)) {
                            foreach ($propertyValue as $propertyParamValue) {
                                $routeParameters[$param][] = $propertyParamValue;
                            }
                        } else {
                            $routeParameters[$param][] = $propertyValue;
                        }
                    } catch (\Exception $e) {
                        // continue if no default value
                        if (null === $propertyDefault) {
                            continue;
                        }

                        // if default value is fixed string set it and continue
                        if ('@' === $propertyDefault[0]) {
                            $routeParameters[$param][] = substr($propertyDefault, 1);
                            continue;
                        }

                        // otherwise evaluate expression
                        $routeParameters[$param][] = $this->expressionLanguage->evaluate($propertyDefault);
                    }
                }
            }

            // resolve priority
            $priority = $mappingValue->getPriority();
            $priority = null === $priority ? '@'.self::PRIORITY_DEFAULT : $priority;

            // if fixed string
            if ('@' === $priority[0]) {
                $priority = substr($priority, 1);
            } else {
                $priority = $this->expressionLanguage->evaluate($priority, ['obj' => $object]);
            }

            // resolve tags
            $tags = $mappingValue->getTags() ?? [];
            foreach ($tags as $key => $value) {
                if (!is_string($value)) {
                    continue;
                }

                if ('@' === $value[0]) {
                    $tags[$key] = substr($value, 1);
                } else {
                    $tags[$key] = $this->expressionLanguage->evaluate($value, ['obj' => $object]);
                }
            }

            if (isset($tags[self::ROUTE_TAG]) && $routeName !== $tags[self::ROUTE_TAG]) {
                continue;
            }

            // get paramteres cartesian
            $parameterCombinations = $this->getCartesianProduct($routeParameters);

            // add route data to urls list
            foreach ($parameterCombinations as $parametersCombination) {
                $urls[] = [
                    'route' => $routeName,
                    'params' => $parametersCombination,
                    'priority' => $priority,
                    'tags' => $tags,
                ];
            }
        }
    }

    protected function getObjectClass(object $object): string
    {
        return '\\' . ltrim(get_class($object), '\\');
    }

    /**
     * @return false|string
     */
    protected function getParentClass(string $class)
    {
        if (false === $parentClass = get_parent_class($class)) {
            return false;
        }

        return '\\' . ltrim($parentClass, '\\');
    }

    /**
     * @return array
     */
    public function getCartesianProduct(array $input = [])
    {
        // filter out empty values
        $input = array_filter($input);
        $result = [[]];

        foreach ($input as $key => $values) {
            $append = [];

            foreach ($result as $product) {
                foreach ($values as $item) {
                    $product[$key] = $item;
                    $append[] = $product;
                }
            }

            $result = $append;
        }

        return $result;
    }
}
