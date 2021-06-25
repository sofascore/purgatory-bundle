<?php

namespace SofaScore\Purgatory\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link * http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     *
     * @psalm-suppress PossiblyNullReference
     * @psalm-suppress UndefinedMethod
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('purgatory');
        $rootNode = $treeBuilder->getRootNode();

        // Here you should define the parameters that are allowed to
        // configure your bundle. See the documentation linked above for
        // more information on that topic.
        $rootNode
            ->children()
            ->arrayNode('cache')
            ->addDefaultsIfNotSet()
            ->children()
            ->booleanNode('debug')
            ->defaultNull()
            ->end()// debug
            ->end()
            ->end()// cache
            ->end();

        return $treeBuilder;
    }
}
