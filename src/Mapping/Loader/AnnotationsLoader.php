<?php

namespace Sofascore\PurgatoryBundle\Mapping\Loader;

use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\ObjectManager;
use Sofascore\PurgatoryBundle\Annotation\Properties;
use Sofascore\PurgatoryBundle\Annotation\PurgeOn;
use Sofascore\PurgatoryBundle\AnnotationReader\Reader;
use Sofascore\PurgatoryBundle\Mapping\MappingCollection;
use Sofascore\PurgatoryBundle\Mapping\MappingValue;
use Sofascore\PurgatoryBundle\Mapping\PropertySubscription;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;

class AnnotationsLoader implements LoaderInterface, WarmableInterface
{
    protected RouterInterface $router;
    protected Configuration $config;
    protected ControllerResolverInterface $controllerResolver;
    protected Reader $annotationReader;
    protected ObjectManager $manager;

    public function __construct(
        Configuration $config,
        RouterInterface $router,
        ControllerResolverInterface $controllerResolver,
        Reader $annotationReader,
        ObjectManager $manager
    ) {
        $this->config = $config;
        $this->router = $router;
        $this->controllerResolver = $controllerResolver;
        $this->annotationReader = $annotationReader;
        $this->manager = $manager;
    }

    /**
     * @throws \ReflectionException
     */
    public function load(): MappingCollection
    {
        if (null === $this->config->getCacheDir()) {
            return $this->loadMappings();
        }

        // instantiate cache
        $cache = new ConfigCache(
            $this->config->getCacheDir() . '/purgatory/mappings/collection.php', $this->config->getDebug()
        );

        // write cache if not fresh
        if (!$cache->isFresh()) {
            // dump cache
            $mappings = $this->loadMappings();
            $cache->write(
                '<?php return ' . var_export($mappings, true) . ';',
                $this->router->getRouteCollection()->getResources()
            );
        }

        // fetch from cache
        return require $cache->getPath();
    }

    /**
     * Warms up the cache.
     *
     * @param string $cacheDir The cache directory
     * @return string[]
     * @throws \ReflectionException
     */
    public function warmUp($cacheDir): array
    {
        // save current cache dir
        $currentCacheDir = $this->config->getCacheDir();

        // set new cache dir
        $this->config->setCacheDir($cacheDir);
        // do warmup
        $this->load();

        // restore cache dir
        $this->config->setCacheDir($currentCacheDir);

        return [];
    }

    /**
     * @throws \ReflectionException
     * @throws \Exception
     */
    private function loadMappings(): MappingCollection
    {
        $mappingCollection = new MappingCollection();
        $routeIgnorePatterns = $this->config->getRouteIgnorePatterns();
        $routes = $this->router->getRouteCollection();
        $subscriptions = [];

        /**
         * @var string $routeName
         * @var Route $route
         */
        foreach ($routes as $routeName => $route) {
            // if route name matches one of the route ignore patterns ignore it
            foreach ($routeIgnorePatterns as $pattern) {
                if (preg_match($pattern, $routeName)) {
                    continue 2;
                }
            }

            $controllerCallable = $this->resolveController($route->getDefault('_controller'));

            // if controller cannot be resolved, skip route
            if (false === $controllerCallable) {
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
            $class = '\\' . ltrim($subscription->getClass(), '\\');
            $property = $subscription->getProperty();

            // set key
            $key = $class . (null !== $property ? '::' . $property : '');

            // create mapping from subscription
            $mappingValue = new MappingValue($subscription->getRouteName());
            $mappingValue->setParameters($subscription->getParameters());
            $mappingValue->setIf($subscription->getIf());
            $mappingValue->setTags($subscription->getTags());

            // add it to collection
            $mappingCollection->add($key, $mappingValue);
        }

        return $mappingCollection;
    }

    protected function resolveController(?string $controllerPath): callable|false
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
     * @return PropertySubscription[]
     * @throws \ReflectionException|\Sofascore\PurgatoryBundle\AnnotationReader\ReaderException
     */
    private function parseControllerMappings(
        callable $controllerCallable,
        string $routeName,
        Route $route,
        array &$subscriptions
    ): array {
        if (!is_array($controllerCallable)) {
            return [];
        }

        [$controller, $method] = $controllerCallable;

        $reflectionMethod = new \ReflectionMethod($controller, $method);
        $methodAnnotations = $this->annotationReader->getAnnotations($reflectionMethod);

        foreach ($methodAnnotations as $class => $annotations) {
            if ($class === PurgeOn::class) {
                foreach ($annotations as $annotation) {
                    $this->parsePurgeOn($annotation, $routeName, $route, $subscriptions);
                }
            }
        }

        return $subscriptions;
    }

    public function parsePurgeOn(PurgeOn $annotation, $routeName, $route, array &$subscriptions): void
    {
        $resolveParameters = static function ($parameters): array {
            $resolved = [];

            foreach ($parameters as $key => $param) {
                $resolved[$key] = !is_array($param) ? [$param] : $param;
            }

            return $resolved;
        };

        $createSubscriptionFromAnnotation = function (PurgeOn $annotation, $property, $routeName, $route) use (
            $resolveParameters
        ) {
            // create subscription
            $subscription = new PropertySubscription($annotation->getObject(), $property);
            $subscription->setParameters($annotation->getParameters());
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

        // add subscription for each property
        foreach ($annotation->getProperties() ?? [] as $property) {
            $subscription = $createSubscriptionFromAnnotation($annotation, $property, $routeName, $route);

            // add to subscriptions list
            $subscriptions[] = $subscription;
        }

        // if no properties, add class subscription
        if (0 === count($annotation->getProperties() ?? [])) {
            $subscription = $createSubscriptionFromAnnotation($annotation, null, $routeName, $route);

            // add to subscriptions list
            $subscriptions[] = $subscription;
        }
    }

    /**
     * @param PropertySubscription[] $subscriptions
     *
     * @return PropertySubscription[]
     * @throws \Exception
     */
    public function resolveSubscriptions(array $subscriptions): array
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
            $property = (string)$subscription->getProperty();
            $subProperty = null;

            // process properties and embedded properties
            while ($property !== '') {
                // if property is class field
                if (null === $subProperty && in_array($property, $fieldNames, true)) {
                    // add it to resolved
                    $resolved[] = $subscription;

                    // and move on to next property
                    continue 2;
                }

                if (in_array($property, $associations, true) && $metadata->isSingleValuedAssociation(
                        $property
                    ) && !$metadata->isAssociationInverseSide($property)) {
                    $resolved[] = $subscription;
                }

                // if property is class association
                if (in_array($property, $associations, true)) {
                    // If it's TO_ONE relation without inverse, don't throw
                    if (!$metadata->getAssociationMappedByTargetField(
                            $property
                        ) && $metadata->isSingleValuedAssociation($property)) {
                        continue 2;
                    }

                    // if association does not have inverse, refresh cannot successfully set parameters context
                    $this->validateAssociationInverse($property, $metadata);

                    // get data
                    $associationClass = (string) $metadata->getAssociationTargetClass($property);
                    $associationTarget = $metadata->getAssociationMappedByTargetField($property);
                    $associationParameters = [];
                    $associationTags = [];

                    // update parameters with association target
                    foreach ($subscription->getParameters() ?? [] as $key => $values) {
                        foreach ($values as $index => $value) {
                            // if value is not fixed string
                            $prefix = strpos($value, '@') !== 0 ? $associationTarget . '.' : '';
                            // add association target as prefix
                            $associationParameters[$key][$index] = $prefix . $value;
                        }
                    }

                    // update tags with association target
                    foreach ($subscription->getTags() ?? [] as $key => $value) {
                        // if expression, replace obj
                        if (is_string($value) && '@' !== $value[0]) {
                            $value = str_replace('obj', 'obj.' . $this->getGetterCall($associationTarget), $value);
                        }

                        // set association tag value
                        $associationTags[$key] = $value;
                    }

                    // update if condition with association target
                    $associationIf = null;
                    if (null !== $if = $subscription->getIf()) {
                        $associationIf = str_replace(
                            'obj',
                            'obj.' . $this->getGetterCall($associationTarget),
                            $if
                        );
                    }

                    // add association subscription to subscriptions to process list
                    $associationSubscription = new PropertySubscription($associationClass, $subProperty);
                    $associationSubscription->setParameters($associationParameters);
                    $associationSubscription->setIf($associationIf);
                    $associationSubscription->setTags($associationTags);

                    // set association subscription route
                    $associationSubscription->setRouteName($subscription->getRouteName());
                    $associationSubscription->setRoute($subscription->getRoute());

                    $subscriptions[] = $associationSubscription;

                    // continue to next property
                    continue 2;
                }

                // if no subproperty, check for embedded class fields (field.subfield)
                if (null === $subProperty) {
                    // init flag
                    $hadSubProperties = false;

                    foreach ($fieldNames as $fieldName) {
                        // if field name starts with property
                        if (strpos($fieldName, $property) === 0) {
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
     * @throws \ReflectionException
     */
    private function resolveClassMethod(PropertySubscription $subscription, string $method, array &$subscriptions): void
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
     * @throws \ReflectionException
     * @throws \Sofascore\PurgatoryBundle\AnnotationReader\ReaderException
     */
    private function getMethodProperties($class, string $method): array
    {
        $reflectionMethod = new \ReflectionMethod($class, $method);
        $methodAnnotations = $this->annotationReader->getAnnotations($reflectionMethod);
        $properties = [];

        foreach ($methodAnnotations as $class => $annotations) {
            // skip everything that's not Properties annotation
            if (Properties::class !== $class) {
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
            } // else if property is class property def
            else {
                // separate class and property def
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
     * @throws \Exception
     */
    protected function validateAssociationInverse(string $association, ClassMetadata $metadata): void
    {
        if (null === $metadata->getAssociationMappedByTargetField($association)) {
            throw new \Exception(
                sprintf(
                    "Association '%s' of class '%s' to class '%s' has no `mapped by` field.",
                    $association,
                    $metadata->getReflectionClass()->getName(),
                    (string) $metadata->getAssociationTargetClass($association)
                )
            );
        }
    }

    protected function setSubscriptionParametersFromRoute(PropertySubscription $subscription, Route $route): void
    {
        $compiledRoute = $route->compile();

        $parameters = [];
        foreach ($compiledRoute->getPathVariables() as $param) {
            $parameters[$param] = str_replace('_', '.', $param);
        }

        $subscription->setParameters($parameters);
    }

    protected function getGetterCall(string $property): string
    {
        return 'get' . ucfirst($property) . '()';
    }

    public function getConfig(): Configuration
    {
        return $this->config;
    }

    public function setConfig(Configuration $config): void
    {
        $this->config = $config;
    }
}
