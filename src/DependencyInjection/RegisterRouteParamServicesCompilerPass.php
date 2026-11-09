<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\DependencyInjection;

use Sofascore\PurgatoryBundle\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class RegisterRouteParamServicesCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $routeParamServiceRefs = [];
        $usedAliases = [];
        /** @var list<array{alias: string, method?: string}> $attributes */
        foreach ($container->findTaggedServiceIds('purgatory.route_parameter_service', true) as $id => $attributes) {
            foreach ($attributes as $attribute) {
                $alias = $attribute['alias'];
                $method = $attribute['method'] ?? '__invoke';

                if (isset($usedAliases[$alias])) {
                    throw new RuntimeException(\sprintf('The alias "%s" is already used by "%s".', $alias, $usedAliases[$alias]));
                }

                if (null !== ($class = $container->getDefinition($id)->getClass())
                    && ($reflectionClass = $container->getReflectionClass($class, false))
                    && !$reflectionClass->hasMethod('__call')
                    && (!$reflectionClass->hasMethod($method) || !$reflectionClass->getMethod($method)->isPublic())
                ) {
                    throw new RuntimeException(\sprintf('Invalid route parameter service, the method "%s::%s()" does not exist or is not public.', $reflectionClass->name, $method));
                }

                $routeParamServiceRefs[$alias] = (new Definition(\Closure::class, [[new Reference($id), $method]]))
                    ->setFactory([\Closure::class, 'fromCallable']);

                $usedAliases[$alias] = \sprintf('%s::%s', $id, $method);
            }
        }

        // static method callables and closures don't need a service, so the resolver is kept even without any
        $container->getDefinition('sofascore.purgatory.route_parameter_resolver.dynamic')
            ->replaceArgument(0, ServiceLocatorTagPass::register($container, $routeParamServiceRefs));
    }
}
