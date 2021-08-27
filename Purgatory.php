<?php

namespace SofaScore\Purgatory;

use Exception;
use SofaScore\Purgatory\Mapping\Loader\LoaderInterface;
use SofaScore\Purgatory\Mapping\MappingCollection;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

class Purgatory
{
    public const ROUTE_TAG = 'route';

    private ?MappingCollection $mappings = null;

    private LoaderInterface $mappingsLoader;

    private PropertyAccessorInterface $propertyAccessor;

    private ExpressionLanguage $expressionLanguage;

    public function __construct(LoaderInterface $mappingsLoader, PropertyAccessorInterface $propertyAccessor)
    {
        $this->mappingsLoader = $mappingsLoader;
        $this->propertyAccessor = $propertyAccessor;
        $this->expressionLanguage = new ExpressionLanguage();
    }

    private function getMappings(): MappingCollection
    {
        if (null === $this->mappings) {
            $this->mappings = $this->mappingsLoader->load();
        }

        return $this->mappings;
    }

    /**
     * Returns array of url definitions:
     * [
     *      [ route => 'route_name', params => ['param1' => 'param_value', ... ] ],
     *      ...
     * ].
     *
     * @param mixed $object            Object that was changed
     * @param array $changedProperties List of property paths (ex. ['status.description', 'userCount', ...])
     *
     */
    public function getUrlsToPurge($object, array $changedProperties): array
    {
        // check if there are changes
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

            // move to next property if it exists
            if (count($changedProperties) > 0) {
                $stack[] = [$this->getObjectClass($object), array_shift($changedProperties)];
            }
        }

        return $urls;
    }


    private function processMappingValues($object, array $mappingValues, array &$urls): void
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
                    if (strpos($property, '@') === 0) {
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
                    } catch (Exception $e) {
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

            // get parameters cartesian
            $parameterCombinations = $this->getCartesianProduct($routeParameters);

            // add route data to urls list
            foreach ($parameterCombinations as $parametersCombination) {
                $urls[] = [
                    'route' => $routeName,
                    'params' => $parametersCombination,
                    'tags' => $tags,
                ];
            }
        }
    }

    private function getObjectClass(object $object): string
    {
        return '\\' . ltrim(get_class($object), '\\');
    }

    /**
     * @return false|string
     */
    private function getParentClass(string $class)
    {
        if (false === $parentClass = get_parent_class($class)) {
            return false;
        }

        return '\\' . ltrim($parentClass, '\\');
    }

    private function getCartesianProduct(array $input = []): array
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
