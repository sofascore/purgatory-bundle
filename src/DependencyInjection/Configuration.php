<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\DependencyInjection;

use Sofascore\PurgatoryBundle2\Purger\PurgerInterface;
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
                ->arrayNode('route_ignore_patterns')
                    ->info('Route names that match the given regular expressions will be ignored.')
                    ->example(['/^_profiler/', '/^_wdt/'])
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
                ->arrayNode('purger')
                    ->addDefaultsIfNotSet()
                    ->beforeNormalization()
                        ->ifTrue(static fn (mixed $purger): bool => \is_string($purger))
                        ->then(static fn (string $purger): array => ['name' => $purger])
                    ->end()
                    ->validate()
                        ->ifTrue(static fn (array $purger): bool => 'symfony' === $purger['name'] && null === $purger['host'])
                        ->thenInvalid('A host must be provided when using the Symfony purger.')
                    ->end()
                    ->children()
                        ->scalarNode('name')
                            ->info(sprintf('A service that implements the "%s" interface', PurgerInterface::class))
                            ->example('symfony')
                            ->defaultNull()
                        ->end()
                        ->scalarNode('host')
                            ->info('The host from which URLs should be purged')
                            ->defaultNull()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
