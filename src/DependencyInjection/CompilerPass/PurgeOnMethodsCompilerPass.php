<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\DependencyInjection\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class PurgeOnMethodsCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $classMap = [];

        foreach ($container->findTaggedServiceIds('purgatory.purge_on') as $id => $tags) {
            $classMap[$id] = array_column($tags, 'method');
        }

        if ($classMap) {
            $container->getDefinition('sofascore.purgatory.controller_metadata_provider')
                ->replaceArgument(1, $classMap);
        }
    }
}
