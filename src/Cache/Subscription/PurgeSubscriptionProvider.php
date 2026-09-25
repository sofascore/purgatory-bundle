<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Cache\Subscription;

use Doctrine\Persistence\ManagerRegistry;
use Psr\Container\ContainerInterface;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\CompoundValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\DynamicValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\ExpressionValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\PropertyValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\ValuesInterface;
use Sofascore\PurgatoryBundle\Attribute\Target\TargetInterface;
use Sofascore\PurgatoryBundle\Cache\PropertyResolver\SubscriptionResolverInterface;
use Sofascore\PurgatoryBundle\Cache\RouteMetadata\RouteMetadata;
use Sofascore\PurgatoryBundle\Cache\RouteMetadata\RouteMetadataProviderInterface;
use Sofascore\PurgatoryBundle\Cache\TargetResolver\TargetResolverInterface;
use Sofascore\PurgatoryBundle\Exception\EntityMetadataNotFoundException;
use Sofascore\PurgatoryBundle\Exception\InvalidDynamicValuesClosureException;
use Sofascore\PurgatoryBundle\Exception\InvalidIfCallableException;
use Sofascore\PurgatoryBundle\Exception\InvalidIfExpressionException;
use Sofascore\PurgatoryBundle\Exception\MissingRequiredRouteParametersException;
use Sofascore\PurgatoryBundle\Exception\TargetSubscriptionNotResolvableException;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\SyntaxError;

/**
 * @internal Used during cache warmup
 */
final class PurgeSubscriptionProvider implements PurgeSubscriptionProviderInterface
{
    /**
     * @param iterable<SubscriptionResolverInterface>  $subscriptionResolvers
     * @param iterable<RouteMetadataProviderInterface> $routeMetadataProviders
     */
    public function __construct(
        private readonly iterable $subscriptionResolvers,
        private readonly iterable $routeMetadataProviders,
        private readonly ManagerRegistry $managerRegistry,
        private readonly ContainerInterface $targetResolverLocator,
        private readonly ?ExpressionLanguage $expressionLanguage,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function provide(): iterable
    {
        foreach ($this->routeMetadataProviders as $routeMetadataProvider) {
            yield from $this->provideFromMetadata($routeMetadataProvider);
        }
    }

    /**
     * @return iterable<PurgeSubscription>
     */
    private function provideFromMetadata(RouteMetadataProviderInterface $routeMetadataProvider): iterable
    {
        foreach ($routeMetadataProvider->provide() as $routeMetadata) {
            $purgeOn = $routeMetadata->purgeOn;

            if (null !== $purgeOn->if) {
                $this->validateIf($purgeOn->if, $routeMetadata->routeName, $purgeOn->class);
            }

            // if route parameters are not specified, they are same as path variables
            if (null === $purgeOn->routeParams) {
                /** @var list<string> $pathVariables */
                $pathVariables = $routeMetadata->route->compile()->getPathVariables();

                /** @var array<string, ValuesInterface> $routeParams */
                $routeParams = [];

                foreach ($pathVariables as $pathVariable) {
                    $routeParams[$pathVariable] = new PropertyValues($pathVariable);
                }
            } else {
                foreach ($purgeOn->routeParams as $values) {
                    $this->validateRouteParamValues($values, $routeMetadata->routeName);
                }
                $this->validateRouteParams(array_keys($purgeOn->routeParams), $routeMetadata);
                $routeParams = $purgeOn->routeParams;
            }

            if (null === $purgeOn->target) {
                yield new PurgeSubscription(
                    class: $purgeOn->class,
                    property: null,
                    routeParams: $routeParams,
                    routeName: $routeMetadata->routeName,
                    route: $routeMetadata->route,
                    actions: $purgeOn->actions,
                    if: $purgeOn->if,
                );

                continue;
            }

            $class = $purgeOn->class;

            if (null === $entityMetadata = $this->managerRegistry->getManagerForClass($class)?->getClassMetadata($class)) {
                throw new EntityMetadataNotFoundException($class);
            }

            /** @var TargetResolverInterface<TargetInterface> $targetResolver */
            $targetResolver = $this->targetResolverLocator->get($purgeOn->target::class);

            foreach ($targetResolver->resolve($purgeOn->target, $routeMetadata) as $property) {
                $targetResolved = false;

                foreach ($this->subscriptionResolvers as $resolver) {
                    yield from $subscriptions = $resolver->resolveSubscription($routeMetadata, $entityMetadata, $routeParams, $property);

                    $targetResolved = $targetResolved || $subscriptions->getReturn();
                }

                if (!$targetResolved) {
                    throw new TargetSubscriptionNotResolvableException($routeMetadata->routeName, $class, $property);
                }
            }
        }
    }

    /**
     * Check if all required route params are present in PurgeOn.
     *
     * @param non-empty-list<string> $routeParams
     */
    private function validateRouteParams(array $routeParams, RouteMetadata $routeMetadata): void
    {
        /** @var list<string> $pathVariables */
        $pathVariables = $routeMetadata->route->compile()->getPathVariables();
        $route = $routeMetadata->route;

        $requiredRouteParams = array_filter(
            array: $pathVariables,
            callback: static fn (string $var): bool => !$route->hasDefault($var),
        );

        if ([] !== $missingRouteParams = array_diff($requiredRouteParams, $routeParams)) {
            throw new MissingRequiredRouteParametersException(
                routeName: $routeMetadata->routeName,
                missingRouteParams: array_values($missingRouteParams),
            );
        }
    }

    /**
     * @param callable-array<string>|\Closure|Expression $if
     */
    private function validateIf(array|\Closure|Expression $if, string $routeName, string $entity): void
    {
        if ($if instanceof \Closure) {
            $this->validateIfClosure($if, $routeName, $entity);
        } elseif (\is_array($if)) {
            $this->validateIfSignature(new \ReflectionMethod($if[0], $if[1]), 'method', $routeName, $entity);
        } else {
            $this->validateExpression($if, $routeName);
        }
    }

    private function validateIfClosure(\Closure $closure, string $routeName, string $entity): void
    {
        $reflection = new \ReflectionFunction($closure);

        if (null !== $violation = self::getClosureViolation($reflection)) {
            throw new InvalidIfCallableException($routeName, $violation);
        }

        $this->validateIfSignature($reflection, 'closure', $routeName, $entity);
    }

    private function validateIfSignature(\ReflectionFunctionAbstract $reflection, string $kind, string $routeName, string $entity): void
    {
        $returnType = $reflection->getReturnType();

        if (!$returnType instanceof \ReflectionNamedType
            || $returnType->allowsNull()
            || !\in_array($returnType->getName(), ['bool', 'true', 'false'], true)
        ) {
            throw new InvalidIfCallableException($routeName, \sprintf('The %s must declare a non-nullable bool return type.', $kind));
        }

        if (1 !== $reflection->getNumberOfParameters()) {
            throw new InvalidIfCallableException($routeName, \sprintf('The %s must have exactly one parameter.', $kind));
        }

        $parameterType = $reflection->getParameters()[0]->getType();

        if (!$parameterType instanceof \ReflectionNamedType
            || $parameterType->allowsNull()
            || !is_a($entity, $parameterType->getName(), true)
        ) {
            throw new InvalidIfCallableException($routeName, \sprintf('The %s parameter must be typed as "%s" or one of its parent types.', $kind, $entity));
        }
    }

    private function validateRouteParamValues(ValuesInterface $values, string $routeName): void
    {
        if ($values instanceof CompoundValues) {
            foreach ($values->values as $nestedValues) {
                $this->validateRouteParamValues($nestedValues, $routeName);
            }
        } elseif ($values instanceof ExpressionValues) {
            $this->validateExpression($values->expression, $routeName);
        } elseif ($values instanceof DynamicValues && $values->provider instanceof \Closure) {
            $reflection = new \ReflectionFunction($values->provider);

            if (null !== $violation = self::getClosureViolation($reflection)) {
                throw new InvalidDynamicValuesClosureException($routeName, $violation);
            }

            if ($reflection->getNumberOfRequiredParameters() > 1) {
                throw new InvalidDynamicValuesClosureException($routeName, 'The closure must not require more than one parameter.');
            }
        }
    }

    /**
     * Closures are serialized with DeepClone, which only supports static anonymous closures declared in constant expressions.
     */
    private static function getClosureViolation(\ReflectionFunction $reflection): ?string
    {
        return match (true) {
            null !== $reflection->getClosureThis() => 'The closure must be static.',
            [] !== $reflection->getClosureUsedVariables() => 'The closure must not capture variables.',
            !$reflection->isAnonymous() => 'First-class callables are not supported, use a static closure instead.',
            default => null,
        };
    }

    private function validateExpression(Expression $expression, string $routeName): void
    {
        try {
            $this->expressionLanguage?->lint($expression, ['obj']);
        } catch (SyntaxError $e) {
            throw new InvalidIfExpressionException($expression, $routeName, $e);
        }
    }
}
