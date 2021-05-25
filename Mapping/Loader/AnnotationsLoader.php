<?php

namespace SofaScore\CacheRefreshBundle\Mapping\Loader;

use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\ObjectManager;
use SofaScore\CacheRefreshBundle\Annotation\Properties;
use SofaScore\CacheRefreshBundle\Annotation\SubscribeTo;
use SofaScore\CacheRefreshBundle\AnnotationReader\Reader as AnnotationReader;
use SofaScore\CacheRefreshBundle\Mapping\MappingCollection;
use SofaScore\CacheRefreshBundle\Mapping\MappingValue;
use SofaScore\CacheRefreshBundle\Mapping\PropertySubscription;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;

class AnnotationsLoader implements LoaderInterface, WarmableInterface
{
    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @var Configuration
     */
    protected $config;

    /**
     * @var ControllerResolverInterface
     */
    protected $controllerResolver;

    /**
     * @var AnnotationReader
     */
    protected $annotationReader;

    /**
     * @var ObjectManager
     */
    protected $manager;

    public function __construct(
        Configuration $config,
        RouterInterface $router,
        ControllerResolverInterface $controllerResolver,
        AnnotationReader $annotationReader,
        ObjectManager $manager
    ) {
        $this->config = $config;
        $this->router = $router;
        $this->controllerResolver = $controllerResolver;
        $this->annotationReader = $annotationReader;
        $this->manager = $manager;
    }

    /**
     * @return MappingCollection
     */
    public function load()
    {
        if (null === $this->config->getCacheDir()) {
            return $this->loadMappings();
        }

        // instantiate cache
        $cache = new ConfigCache(
            $this->config->getCacheDir().'/cache_refresh/mappings/collection.php', $this->config->getDebug()
        );

        // write cache if not fresh
        if (!$cache->isFresh()) {
            // dump cache
            $mappings = $this->loadMappings();
            $cache->write(
                '<?php return '.var_export($mappings, true).';',
                $this->router->getRouteCollection()->getResources()
            );
        }

        // fetch from cache
        $mappings = require $cache->getPath();

        return $mappings;
    }

    /**
     * Warms up the cache.
     *
     * @param string $cacheDir The cache directory
     */
    public function warmUp($cacheDir)
    {
        // save current cache dir
        $currentCacheDir = $this->config->getCacheDir();

        // set new cache dir
        $this->config->setCacheDir($cacheDir);
        // do warmup
        $this->load();

        // restore cache dir
        $this->config->setCacheDir($currentCacheDir);
    }

    public function loadMappings(): MappingCollection
    {
        $mappingCollection = new MappingCollection();
        $routeIgnorePatterns = $this->config->getRouteIgnorePatterns();
        $routes = $this->router->getRouteCollection();
        $subscriptions = [];

        /**
         * @var string $routeName
         * @var Route  $route
         */
        foreach ($routes as $routeName => $route) {
            // if route name matches one of the route ignore patterns ignore it
            foreach ($routeIgnorePatterns as $pattern) {
                if (preg_match($pattern, $routeName)) {
                    continue 2;
                }
            }

            // if controller cannot be resolved, skip route
            if (false === $controllerCallable = $this->resolveController($route->getDefault('_controller'))) {
                continue;
            }

            // get class/property subscriptions
            $this->parseControllerMappings($controllerCallable, $routeName, $route, $subscriptions);
        }

        // resolve subscription classes and properties
        $subscriptions = $this->resolveSubscriptions($subscriptions);

        // add mappings
        foreach ($subscriptions as $subscription) {
            // prepare class and property
            $class = '\\'.ltrim($subscription->getClass(), '\\');
            $property = $subscription->getProperty();

            // set key
            $key = $class.(null !== $property ? '::'.$property : '');

            // create mapping from subscription
            $mappingValue = new MappingValue($subscription->getRouteName());
            $mappingValue->setParameters($subscription->getParameters());
            $mappingValue->setPriority($subscription->getPriority());
            $mappingValue->setIf($subscription->getIf());
            $mappingValue->setTags($subscription->getTags());

            // add it to collection
            $mappingCollection->add($key, $mappingValue);
        }

        return $mappingCollection;
    }

    /**
     * @return callable|false
     */
    protected function resolveController(?string $controllerPath)
    {
        if (null === $controllerPath) {
            return false;
        }

        // set controller path
        $request = new Request([], [], ['_controller' => $controllerPath]);

        try {
            // resolve controller path
            return $this->controllerResolver->getController($request);
        } catch (\Exception $e) {
            // if error happens skip route
            return false;
        }
    }

    /**
     * @param callable $controllerCallable
     * @param string   $routeName
     * @param Route    $route
     *
     * @return PropertySubscription[]
     */
    public function parseControllerMappings($controllerCallable, $routeName, $route, array &$subscriptions)
    {
        if (!is_array($controllerCallable)) {
            return [];
        }

        [$controller, $method] = $controllerCallable;

        $reflectionMethod = new \ReflectionMethod($controller, $method);
        $methodAnnotations = $this->annotationReader->getAnnotations($reflectionMethod);

        foreach ($methodAnnotations as $class => $annotations) {
            // parse SubscribeTo annotation
            if (false !== strpos($class, 'SofaScore\CacheRefreshBundle\Annotation\SubscribeTo')) {
                foreach ($annotations as $annotation) {
                    $this->parseSubscribeTo($annotation, $routeName, $route, $subscriptions);
                }
            }
        }

        return $subscriptions;
    }

    /**
     * @param string $routeName
     * @param Route  $route
     *
     * @return PropertySubscription[]
     */
    public function parseSubscribeTo(SubscribeTo $annotation, $routeName, $route, array &$subscriptions)
    {
        /**
         * @param $parameters
         *
         * @return array
         */
        $resolveParameters = function ($parameters) {
            $resolved = [];

            foreach ($parameters as $key => $param) {
                $resolved[$key] = !is_array($param) ? [$param] : $param;
            }

            return $resolved;
        };

        /**
         * @param SubscribeTo $annotation
         * @param             $property
         * @param             $routeName
         * @param             $route
         *
         * @return PropertySubscription
         */
        $createSubscriptionFromAnnotation = function (SubscribeTo $annotation, $property, $routeName, $route) use ($resolveParameters) {
            // create subscription
            $subscription = new PropertySubscription($annotation->getObject(), $property);
            $subscription->setParameters($annotation->getParameters());
            $subscription->setPriority($annotation->getPriority());
            $subscription->setIf($annotation->getIf());
            $subscription->setTags($annotation->getTags());

            // if parameters are null, set parameters from route
            if (null === $annotation->getParameters()) {
                $this->setSubscriptionParametersFromRoute($subscription, $route);
            }

            // make parameter values arrays
            $resolvedParameters = $resolveParameters($subscription->getParameters());
            $subscription->setParameters($resolvedParameters);

            // set route
            $subscription->setRouteName($routeName);
            $subscription->setRoute($route);

            return $subscription;
        };

        // route will not be processd if it has routes array specified and current route name
        // is not in it
        if (null !== $annotation->getRoutes() && !in_array($routeName, $annotation->getRoutes())) {
            return;
        }

        // add subscription for each property
        foreach ($annotation->getProperties() as $property) {
            $subscription = $createSubscriptionFromAnnotation($annotation, $property, $routeName, $route);

            // add to subscriptions list
            $subscriptions[] = $subscription;
        }

        // if no properties, add class subscription
        if (count($annotation->getProperties()) <= 0) {
            $subscription = $createSubscriptionFromAnnotation($annotation, null, $routeName, $route);

            // add to subscriptions list
            $subscriptions[] = $subscription;
        }
    }

    /**
     * @param PropertySubscription[] $subscriptions
     *
     * @return PropertySubscription[]
     */
    public function resolveSubscriptions($subscriptions)
    {
        /** @var PropertySubscription[] $resolved */
        $resolved = [];

        // iterate over subscriptions
        while (count($subscriptions) > 0) {
            /** @var PropertySubscription $subscription */
            $subscription = array_shift($subscriptions);
            $metadata = $this->manager->getClassMetadata($subscription->getClass());

            // set subscription resolved class name
            $subscription->setClass($metadata->getReflectionClass()->getName());

            // if no property
            if (null === $subscription->getProperty()) {
                // add subscription to resolved
                $resolved[] = $subscription;

                // and continue
                continue;
            }

            // get metadata
            $fieldNames = $metadata->getFieldNames();
            $associations = $metadata->getAssociationNames();

            // set initial property data
            $property = $subscription->getProperty();
            $subProperty = null;

            // process properties and embeded properties
            while (strlen($property) > 0) {
                // if property is class field
                if (in_array($property, $fieldNames) && null == $subProperty) {
                    // add it to resolved
                    $resolved[] = $subscription;

                    // and move on to next property
                    continue 2;
                }

                if (in_array($property, $associations) && $metadata->isSingleValuedAssociation($property) && !$metadata->isAssociationInverseSide($property)) {
                    $resolved[] = $subscription;
                }

                // if property is class association
                if (in_array($property, $associations)) {
                    // If it's TO_ONE relation without inverse, don't throw
                    if (!$metadata->getAssociationMappedByTargetField($property) && $metadata->isSingleValuedAssociation($property)) {
                        continue 2;
                    }

                    // if association does not have inverse, refresh cannot successfully set parameters context
                    $this->validateAssociationInverse($property, $metadata);

                    // get data
                    $associationClass = $metadata->getAssociationTargetClass($property);
                    $asscoiationTarget = $metadata->getAssociationMappedByTargetField($property);
                    $associationParameters = [];
                    $associationTags = [];

                    // update parameters with association target
                    foreach ($subscription->getParameters() as $key => $values) {
                        foreach ($values as $index => $value) {
                            // if value is not fixed string
                            $prefix = '@' !== substr($value, 0, 1) ? $asscoiationTarget.'.' : '';
                            // add association target as prefix
                            $associationParameters[$key][$index] = $prefix.$value;
                        }
                    }

                    // update tags with association target
                    foreach ($subscription->getTags() as $key => $value) {
                        // if expression, replace obj
                        if (is_string($value) && '@' !== substr($value, 0, 1)) {
                            $value = str_replace('obj', 'obj.'.$this->getGetterCall($asscoiationTarget), $value);
                        }

                        // set assoctiation tag value
                        $associationTags[$key] = $value;
                    }

                    // update if condition with association target
                    $associationIf = null !== $subscription->getIf() ?
                        str_replace('obj', 'obj.'.$this->getGetterCall($asscoiationTarget),
                            $subscription->getIf()) : null;

                    // update association priority with association target
                    $associationPriority = null !== $subscription->getPriority() ?
                        str_replace('obj', 'obj.'.$this->getGetterCall($asscoiationTarget),
                            $subscription->getPriority()) : null;

                    // add association subscription to subscriptions to process list
                    $associationSubscription = new PropertySubscription($associationClass, $subProperty);
                    $associationSubscription->setParameters($associationParameters);
                    $associationSubscription->setPriority($associationPriority);
                    $associationSubscription->setIf($associationIf);
                    $associationSubscription->setTags($associationTags);

                    // set association subscription route
                    $associationSubscription->setRouteName($subscription->getRouteName());
                    $associationSubscription->setRoute($subscription->getRoute());

                    $subscriptions[] = $associationSubscription;

                    // continue to next property
                    continue 2;
                }

                // if no subproperty, check for embeded class fields (field.subfield)
                if (null === $subProperty) {
                    // init flag
                    $hadSubProperties = false;

                    foreach ($fieldNames as $fieldName) {
                        // if field name starts with property
                        if (substr($fieldName, 0, strlen($property)) === $property) {
                            // set subproperty subscription
                            $subPropertySubscription = clone $subscription;
                            $subPropertySubscription->setProperty($fieldName);

                            // add it to resolved list
                            $resolved[] = $subPropertySubscription;

                            // set flag
                            $hadSubProperties = true;
                        }
                    }

                    // if had subproperties continue to next property
                    if ($hadSubProperties) {
                        continue 2;
                    }
                }

                // if property is method_exists
                if (null === $subProperty && method_exists($subscription->getClass(), $property)) {
                    // resolve method
                    $this->resolveClassMethod($subscription, $property, $subscriptions);

                    // continue to next property
                    continue 2;
                }

                // set property getter
                $getter = $this->getGetterCall($property);
                $getter = str_replace('()', '', $getter);

                // if property getter exists
                if (null === $subProperty && method_exists($subscription->getClass(), $getter)) {
                    // resolve method
                    $this->resolveClassMethod($subscription, $getter, $subscriptions);

                    // continue to next property
                    continue 2;
                }

                // if no subproperties
                if (false === $dotPosition = strrpos($property, '.')) {
                    // add to resolved
                    $resolved[] = $subscription;

                    // continue to next property
                    continue 2;
                }

                // set new properties and try again
                $subProperty = substr($property, $dotPosition + 1);
                $property = substr($property, 0, $dotPosition);
            }
        }

        return $resolved;
    }

    /**
     * @param string $method
     * @param array  $subscriptions
     */
    public function resolveClassMethod(PropertySubscription $subscription, $method, &$subscriptions)
    {
        $methodProperties = $this->getMethodProperties($subscription->getClass(), $method);

        foreach ($methodProperties as $methodProperty) {
            $methodSubscription = clone $subscription;
            $methodSubscription->setProperty($methodProperty['property']);

            if (isset($methodProperty['class'])) {
                $methodSubscription->setClass($methodProperty['class']);
            }

            $subscriptions[] = $methodSubscription;
        }
    }

    /**
     * @param $class
     * @param $method
     *
     * @return array<array>
     */
    public function getMethodProperties($class, $method)
    {
        $reflectionMethod = new \ReflectionMethod($class, $method);
        $methodAnnotations = $this->annotationReader->getAnnotations($reflectionMethod);
        $properties = [];

        foreach ($methodAnnotations as $class => $annotations) {
            // skip evenythiong that's not Properties annotation
            if (false === strpos($class, 'SofaScore\CacheRefreshBundle\Annotation\Properties')) {
                continue;
            }

            /** @var Properties $annotation */
            foreach ($annotations as $annotation) {
                $properties = array_merge($properties, $annotation->getProperties());
            }
        }

        // parse properties
        $parsed = [];

        foreach ($properties as $property) {
            // if property is ordinary property
            if (false === strpos($property, ':')) {
                // add it to parsed list
                $parsed[] = ['property' => $property];
            }
            //
            // else if property is class property def
            else {
                // separate class and property deff
                $parts = explode('.', $property, 2);

                // and add it to parsed list
                $parsed[] = [
                    // class is located in part[0]
                    'class' => $parts[0],
                    // property can also be pure class def, so part[1] can be undefined
                    'property' => $parts[1] ?? null,
                ];
            }
        }

        return $parsed;
    }

    /**
     * @param string $association
     *
     * @throws \Exception
     */
    protected function validateAssociationInverse($association, ClassMetadata $metadata)
    {
        if (null === $metadata->getAssociationMappedByTargetField($association)) {
            throw new \Exception(sprintf("Association '%s' of class '%s' to class '%s' has no `mapped by` field.", $association, $metadata->getReflectionClass()->getName(), $metadata->getAssociationTargetClass($association)));
        }
    }

    protected function setSubscriptionParametersFromRoute(PropertySubscription $subscription, Route $route)
    {
        $parameters = [];
        $compiledRoute = $route->compile();

        foreach ($compiledRoute->getPathVariables() as $param) {
            $paramPath = str_replace('_', '.', $param);
            $parameters[$param] = $paramPath;
        }

        $subscription->setParameters($parameters);
    }

    /**
     * @param string $property
     *
     * @return string
     */
    protected function getGetterCall($property)
    {
        return 'get'.ucfirst($property).'()';
    }

    public function getConfig()
    {
        return $this->config;
    }

    public function setConfig($config)
    {
        $this->config = $config;
    }
}
