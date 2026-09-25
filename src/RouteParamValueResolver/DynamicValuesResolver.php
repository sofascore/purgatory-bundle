<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\RouteParamValueResolver;

use Psr\Container\ContainerInterface;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\DynamicValues;
use Sofascore\PurgatoryBundle\Exception\LogicException;
use Sofascore\PurgatoryBundle\Exception\RuntimeException;
use Sofascore\PurgatoryBundle\RouteProvider\PropertyAccess\PurgatoryPropertyAccessor;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * @implements ValuesResolverInterface<array{0: string|array<mixed>, 1: ?string}>
 */
final class DynamicValuesResolver implements ValuesResolverInterface
{
    /** @var array<string, \Closure> */
    private array $closures = [];

    public function __construct(
        private readonly ContainerInterface $routeParamServiceLocator,
        private readonly PurgatoryPropertyAccessor $propertyAccessor,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public static function for(): string
    {
        return DynamicValues::type();
    }

    /**
     * {@inheritDoc}
     */
    public function resolve(array $unresolvedValues, object $entity): array
    {
        [$provider, $propertyPath] = $unresolvedValues;

        if (\is_array($provider)) {
            // a callable array is a static method, anything else a serialized closure
            $routeParamProvider = \is_callable($provider) ? $provider(...) : $this->getClosure($provider);
        } else {
            try {
                /** @var \Closure $routeParamProvider */
                $routeParamProvider = $this->routeParamServiceLocator->get($provider);
            } catch (ServiceNotFoundException $e) {
                throw new RuntimeException(\sprintf(
                    'A route parameter resolver service with the alias "%s" was not found. Did you forget to use the #[AsPurgatoryResolver] attribute on your service?',
                    $provider,
                ), previous: $e);
            }
        }

        /** @var object|scalar|array<object|scalar> $arg */
        $arg = null === $propertyPath ? $entity : $this->propertyAccessor->getValue($entity, $propertyPath);

        /** @var scalar|list<?scalar>|null $values */
        $values = $routeParamProvider($arg);

        return \is_array($values) ? $values : [$values];
    }

    /**
     * @param array<mixed> $serializedClosure
     */
    private function getClosure(array $serializedClosure): \Closure
    {
        if (isset($this->closures[$key = serialize($serializedClosure)])) {
            return $this->closures[$key];
        }

        if (!($closure = deepclone_from_array($serializedClosure)) instanceof \Closure) {
            throw new LogicException(\sprintf('Expected the "DynamicValues" provider to be a static method callable or a closure, got %s.', get_debug_type($closure)));
        }

        return $this->closures[$key] = $closure;
    }
}
