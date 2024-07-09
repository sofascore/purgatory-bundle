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

        /**
         * @var list<array{class?: class-string}> $tags
         */
        foreach ($container->findTaggedServiceIds('purgatory2.purge_on', true) as $id => $tags) {
            $classMap[$id] = $tags[0]['class'] ?? $container->getDefinition($id)->getClass();
        }

        if ($classMap) {
            $container->getDefinition('sofascore.purgatory2.route_metadata_provider.attribute')
                ->replaceArgument(1, $classMap);
        }
    }
}
