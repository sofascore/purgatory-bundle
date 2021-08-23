<?php

declare(strict_types=1);

namespace SofaScore\Purgatory\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     *
     * @psalm-suppress PossiblyNullReference
     * @psalm-suppress PossiblyUndefinedMethod
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('purgatory');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('purger')
                    ->info('ID of the service implementing the \'SofaScore\Purgatory\Purger\PurgerInterface\' interface.')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->booleanNode('entity_change_listener')
                    ->info('Determines whether entity changes should trigger the configured purge mechanism automatically.')
                    ->defaultTrue()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
