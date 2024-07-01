<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\RouteParamValueResolver;

use Psr\Container\ContainerInterface;
use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\DynamicValues;
use Sofascore\PurgatoryBundle2\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * @implements ValuesResolverInterface<array{0: string, 1: ?string, 2: ?string}>
 */
final class DynamicValuesResolver implements ValuesResolverInterface
{
    public function __construct(
        private readonly ContainerInterface $paramResolverServiceLocator,
        private readonly PropertyAccessorInterface $propertyAccessor,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public static function for(): string
    {
        return DynamicValues::class;
    }

    /**
     * {@inheritDoc}
     */
    public function resolve(array $unresolvedValues, object $entity): array
    {
        [$alias, $method, $propertyPath] = $unresolvedValues;

        try {
            /** @var object $paramResolverService */
            $paramResolverService = $this->paramResolverServiceLocator->get($alias);
        } catch (ServiceNotFoundException $e) {
            throw new RuntimeException(sprintf(
                'A route parameter resolver service with the alias "%s" was not found. Did you forget to use the #[AsPurgatoryResolver] attribute on your service?',
                $alias,
            ), previous: $e);
        }

        $method = $method ?? '__invoke';

        /** @var object|mixed $arg */
        $arg = null === $propertyPath ? $entity : $this->propertyAccessor->getValue($entity, $propertyPath);

        if (!method_exists($paramResolverService, $method)) {
            throw new RuntimeException(sprintf('The service "%s" does not have a method named "%s".', $alias, $method));
        }

        /** @var scalar|list<?scalar>|null $values */
        $values = $paramResolverService->{$method}($arg);

        return \is_array($values) ? $values : [$values];
    }
}
