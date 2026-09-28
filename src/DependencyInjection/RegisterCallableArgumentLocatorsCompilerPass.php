<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\DependencyInjection;

use Sofascore\PurgatoryBundle\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\CompoundValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\DynamicValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\ValuesInterface;
use Sofascore\PurgatoryBundle\Cache\RouteMetadata\YamlMetadataProvider;
use Sofascore\PurgatoryBundle\RouteProvider\CallableInvoker;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireCallable;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\TypedReference;

/**
 * Registers a locator of services for the parameters after the first one of "if" and "DynamicValues" callables.
 */
final class RegisterCallableArgumentLocatorsCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('sofascore.purgatory.callable_invoker')) {
            return;
        }

        $locators = [];

        foreach ($this->getPurgeOns($container) as $purgeOn) {
            foreach (self::getCallables($purgeOn) as $callable) {
                try {
                    $key = CallableInvoker::key($callable);
                } catch (\Throwable) {
                    continue; // invalid closures are reported during cache warmup
                }

                if (isset($locators[$key])) {
                    continue;
                }

                if ($callable instanceof \Closure) {
                    $reflection = new \ReflectionFunction($callable);
                    $callerId = $reflection->getName();
                } else {
                    // a change to the method signature must rebuild the container
                    $container->getReflectionClass($callable[0]);
                    $reflection = new \ReflectionMethod($callable[0], $callable[1]);
                    $callerId = implode('::', $callable).'()';
                }

                if ($arguments = $this->getServiceArguments($container, $reflection)) {
                    $locators[$key] = ServiceLocatorTagPass::register($container, $arguments, $callerId);
                }
            }
        }

        $container->getDefinition('sofascore.purgatory.callable_invoker')
            ->replaceArgument(0, ServiceLocatorTagPass::register($container, $locators));
    }

    /**
     * @return iterable<PurgeOn>
     */
    private function getPurgeOns(ContainerBuilder $container): iterable
    {
        /**
         * @var list<array{class?: class-string}> $tags
         */
        foreach ($container->findTaggedServiceIds('purgatory.purge_on', true) as $id => $tags) {
            /** @var class-string $class */
            $class = $tags[0]['class'] ?? $container->getDefinition($id)->getClass();

            if (null === $reflectionClass = $container->getReflectionClass($class, false)) {
                continue;
            }

            foreach ([$reflectionClass, ...$reflectionClass->getMethods()] as $reflection) {
                foreach ($reflection->getAttributes(PurgeOn::class) as $attribute) {
                    try {
                        yield $attribute->newInstance();
                    } catch (\Throwable) {
                        // invalid attributes are reported during cache warmup
                    }
                }
            }
        }

        if ($container->hasDefinition('sofascore.purgatory.route_metadata_provider.yaml')) {
            /** @var list<string> $files */
            $files = $container->getDefinition('sofascore.purgatory.route_metadata_provider.yaml')->getArgument(1);

            try {
                yield from YamlMetadataProvider::loadPurgeOns($files);
            } catch (\Throwable) {
                // invalid files are reported during cache warmup
            }
        }
    }

    /**
     * @return iterable<callable-array<string>|\Closure>
     */
    private static function getCallables(PurgeOn $purgeOn): iterable
    {
        if ($purgeOn->if instanceof \Closure || \is_array($purgeOn->if)) {
            yield $purgeOn->if;
        }

        foreach ($purgeOn->routeParams ?? [] as $values) {
            yield from self::getDynamicValuesProviders($values);
        }
    }

    /**
     * @return iterable<callable-array<string>|\Closure>
     */
    private static function getDynamicValuesProviders(ValuesInterface $values): iterable
    {
        if ($values instanceof CompoundValues) {
            foreach ($values->values as $nestedValues) {
                yield from self::getDynamicValuesProviders($nestedValues);
            }
        } elseif ($values instanceof DynamicValues && !\is_string($values->provider)) {
            yield $values->provider;
        }
    }

    /**
     * Maps the parameters after the first one, which receives the subject, to services, similarly to controller arguments.
     *
     * @return array<string, mixed>
     */
    private function getServiceArguments(ContainerBuilder $container, \ReflectionFunctionAbstract $reflection): array
    {
        $arguments = [];

        foreach (\array_slice($reflection->getParameters(), 1) as $parameter) {
            $type = $parameter->getType();
            $type = $type instanceof \ReflectionNamedType && !$type->isBuiltin() ? $type->getName() : null;

            if ($autowireAttributes = $parameter->getAttributes(Autowire::class, \ReflectionAttribute::IS_INSTANCEOF)) {
                $invalidBehavior = $parameter->allowsNull() ? ContainerInterface::NULL_ON_INVALID_REFERENCE : ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE;
                $attribute = $autowireAttributes[0]->newInstance();
                $value = $container->getParameterBag()->resolveValue($attribute->value);

                if ($attribute instanceof AutowireCallable) {
                    $arguments[$parameter->name] = $attribute->buildDefinition($value, $type, $parameter);
                } elseif ($value instanceof Reference) {
                    $arguments[$parameter->name] = null !== $type ? new TypedReference((string) $value, $type, $invalidBehavior, $parameter->name) : new Reference((string) $value, $invalidBehavior);
                } else {
                    $arguments[$parameter->name] = new Reference('.value.'.$container->hash($value));
                    $container->register((string) $arguments[$parameter->name], 'mixed')
                        ->setFactory('current')
                        ->addArgument([$value]);
                }

                continue;
            }

            if (null === $type) {
                continue; // required parameters that cannot be resolved are reported during cache warmup
            }

            $invalidBehavior = match (true) {
                $parameter->isOptional() => ContainerInterface::IGNORE_ON_INVALID_REFERENCE,
                $parameter->allowsNull() => ContainerInterface::NULL_ON_INVALID_REFERENCE,
                default => ContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE,
            };
            $targetAttribute = null;
            $name = Target::parseName($parameter, $targetAttribute);

            $arguments[$parameter->name] = new TypedReference($type, $type, $invalidBehavior, $name, $targetAttribute ? [$targetAttribute] : []);
        }

        return $arguments;
    }
}
