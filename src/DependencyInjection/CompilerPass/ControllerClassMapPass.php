<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\DependencyInjection\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class ControllerClassMapPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $classMap = [];

        foreach ($container->findTaggedServiceIds('controller.service_arguments', true) as $id => $tags) {
            $classMap[$id] = $container->getDefinition($id)->getClass();
        }

        $container->getDefinition('sofascore.purgatory.mapping.annotation_loader')->replaceArgument(4, $classMap);
    }
}
