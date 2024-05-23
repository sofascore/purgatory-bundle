<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\DependencyInjection\CompilerPass;

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

        if ($classMap) {
            $container->getDefinition('sofascore.purgatory.controller_metadata_provider')
                ->replaceArgument(1, $classMap);
        }
    }
}
