<?php


namespace Sofascore\PurgatoryBundle\DependencyInjection\CompilerPass;


use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class SymfonyPurgerRemovalCompilerPass implements CompilerPassInterface
{

    public function process(ContainerBuilder $container)
    {
        if (!$container->has('http_cache.store')) {
            $container->removeDefinition('sofascore.purgatory.purger.symfony');
        }
    }
}
