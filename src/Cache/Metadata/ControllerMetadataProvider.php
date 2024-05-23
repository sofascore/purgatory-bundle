<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Cache\Metadata;

use Sofascore\PurgatoryBundle2\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle2\Exception\ClassNotResolvableException;
use Sofascore\PurgatoryBundle2\Exception\InvalidPatternException;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal Used during cache warmup
 */
final class ControllerMetadataProvider implements ControllerMetadataProviderInterface
{
    /**
     * @param array<string, class-string> $classMap
     * @param list<non-empty-string>      $routeIgnorePatterns
     */
    public function __construct(
        private readonly RouterInterface $router,
        private readonly array $classMap,
        private readonly array $routeIgnorePatterns,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function provide(): iterable
    {
        $routeCollection = $this->router->getRouteCollection();

        foreach ($routeCollection as $routeName => $route) {
            if ($this->shouldSkipRoute($routeName)) {
                continue;
            }

            /** @var array{0: class-string, 1: string}|string|null $controller */
            $controller = $route->getDefault('_controller');
            if (null === $controller) {
                continue;
            }

            $reflection = $this->resolveControllerCallable($controller);

            foreach ($reflection->getAttributes(PurgeOn::class, \ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
                /** @var PurgeOn $purgeOn */
                $purgeOn = $attribute->newInstance();

                if (null === $purgeOn->route || \in_array($routeName, (array) $purgeOn->route, true)) {
                    yield new ControllerMetadata(
                        routeName: $routeName,
                        route: $route,
                        purgeOn: $purgeOn,
                    );
                }
            }
        }
    }

    /**
     * @param array{0: class-string, 1: string}|string $controller
     */
    private function resolveControllerCallable(array|string $controller): \ReflectionMethod
    {
        if (\is_array($controller)) {
            return new \ReflectionMethod($this->resolveClass($controller[0]), $controller[1]);
        }

        if (!str_contains($controller, '::')) {
            return new \ReflectionMethod($this->resolveClass($controller), '__invoke');
        }

        [$class, $method] = explode('::', $controller, 2);

        return new \ReflectionMethod($this->resolveClass($class), $method);
    }

    /**
     * @return class-string
     */
    private function resolveClass(string $serviceIdOrClass): string
    {
        return $this->classMap[$serviceIdOrClass] ?? throw new ClassNotResolvableException($serviceIdOrClass);
    }

    private function shouldSkipRoute(string $routeName): bool
    {
        foreach ($this->routeIgnorePatterns as $pattern) {
            $result = preg_match($pattern, $routeName);

            if (1 === $result) {
                return true;
            }

            if (false === $result) {
                throw new InvalidPatternException($pattern, $routeName);
            }
        }

        return false;
    }
}
