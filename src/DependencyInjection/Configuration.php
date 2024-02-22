<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('sofascore_purgatory');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
            ->end()
        ;

        return $treeBuilder;
    }
}
