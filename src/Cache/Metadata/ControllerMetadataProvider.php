<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Cache\Metadata;

use Sofascore\PurgatoryBundle2\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle2\Exception\ClassNotResolvableException;
use Sofascore\PurgatoryBundle2\Exception\RouteNotFoundException;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal Used during cache warmup
 */
final class ControllerMetadataProvider implements ControllerMetadataProviderInterface
{
    public function __construct(
        private readonly RouterInterface $router,
        private readonly array $classMap,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function provide(): iterable
    {
        $routeCollection = $this->router->getRouteCollection();

        foreach ($routeCollection as $routeName => $route) {
            // TODO routeIgnorePatterns u nekon od idućih PRova

            $controller = $route->getDefault('_controller');

            if (null === $controller) {
                continue;
            }

            $reflection = $this->resolveControllerCallable($controller);

            foreach ($reflection->getAttributes(PurgeOn::class, \ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
                /** @var PurgeOn $purgeOn */
                $purgeOn = $attribute->newInstance();

                if (null === $purgeOn->route) {
                    yield new ControllerMetadata(
                        routeName: $routeName,
                        route: $route,
                        purgeOn: $purgeOn,
                    );

                    continue;
                }

                $routes = (array) $purgeOn->route;

                foreach ($routes as $name) {
                    yield new ControllerMetadata(
                        routeName: $name,
                        route: $routeCollection->get($name) ?? throw new RouteNotFoundException($name),
                        purgeOn: $purgeOn,
                    );
                }
            }
        }
    }

    private function resolveControllerCallable(array|string $controller): \ReflectionMethod
    {
        if (\is_array($controller) && isset($controller[0], $controller[1])) {
            return new \ReflectionMethod($this->resolveClass($controller[0]), $controller[1]);
        }

        if (!str_contains($controller, '::')) {
            return new \ReflectionMethod($this->resolveClass($controller), '__invoke');
        }

        [$class, $method] = explode('::', $controller, 2);

        return new \ReflectionMethod($this->resolveClass($class), $method);
    }

    private function resolveClass(string $serviceIdOrClass): string
    {
        if (isset($this->classMap[$serviceIdOrClass])) {
            return $this->classMap[$serviceIdOrClass];
        }

        throw new ClassNotResolvableException($serviceIdOrClass);
    }
}
