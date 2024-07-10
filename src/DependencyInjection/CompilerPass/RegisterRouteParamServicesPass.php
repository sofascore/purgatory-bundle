<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\DependencyInjection\CompilerPass;

use Sofascore\PurgatoryBundle2\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Kernel;

final class RegisterRouteParamServicesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $routeParamServiceRefs = [];
        $usedAliases = [];
        /** @var list<array{alias: string, method: string}> $attributes */
        foreach ($container->findTaggedServiceIds('purgatory2.route_parameter_service', true) as $id => $attributes) {
            foreach ($attributes as $attribute) {
                ['alias' => $alias, 'method' => $method] = $attribute;

                if (isset($usedAliases[$alias])) {
                    throw new RuntimeException(sprintf('The alias "%s" is already used by "%s".', $alias, $usedAliases[$alias]));
                }

                $routeParamServiceRefs[$alias] = (new Definition(\Closure::class, [[new Reference($id), $method]]))
                    ->setFactory([\Closure::class, 'fromCallable']);

                if (Kernel::MAJOR_VERSION <= 5) {
                    $container->setDefinition($factoryId = $id.'.'.$method.'.factory', $routeParamServiceRefs[$alias]);
                    $routeParamServiceRefs[$alias] = new Reference($factoryId);
                }

                $usedAliases[$alias] = sprintf('%s::%s', $id, $method);
            }
        }

        if ($routeParamServiceRefs) {
            $container->getDefinition('sofascore.purgatory2.route_parameter_resolver.dynamic')
                ->replaceArgument(0, ServiceLocatorTagPass::register($container, $routeParamServiceRefs));
        } else {
            $container->removeDefinition('sofascore.purgatory2.route_parameter_resolver.dynamic');
        }
    }
}
