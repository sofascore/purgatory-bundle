<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\DependencyInjection;

use Sofascore\PurgatoryBundle2\Purger\PurgerInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Messenger\MessageBusInterface;

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
                ->integerNode('doctrine_middleware_priority')
                    ->info('Explicitly set the priority of Purgatory\'s Doctrine middleware.')
                    ->defaultNull()
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
                ->arrayNode('messenger')
                    ->addDefaultsIfNotSet()
                    ->beforeNormalization()
                        ->ifTrue(static fn (mixed $messenger): bool => \is_string($messenger))
                        ->then(static fn (string $messenger): array => ['transport' => $messenger])
                    ->end()
                    ->validate()
                        ->ifTrue(static fn (array $messenger): bool => !interface_exists(MessageBusInterface::class) && array_filter($messenger))
                        ->thenInvalid('Messenger support cannot be enabled as the component is not installed. Try running "composer require symfony/messenger".')
                    ->end()
                    ->validate()
                        ->ifTrue(static fn (array $messenger): bool => !$messenger['transport'] && $messenger['bus'])
                        ->thenInvalid('Cannot set the messenger bus without defining the transport.')
                    ->end()
                    ->validate()
                        ->ifTrue(static fn (array $messenger): bool => !$messenger['transport'] && $messenger['batch_size'])
                        ->thenInvalid('Cannot set the batch size without defining the transport.')
                    ->end()
                    ->children()
                        ->scalarNode('transport')
                            ->info('Set the name of the messenger transport to use')
                            ->defaultNull()
                            ->cannotBeEmpty()
                        ->end()
                        ->scalarNode('bus')
                            ->info('Set the name of the messenger bus to use')
                            ->defaultNull()
                            ->cannotBeEmpty()
                        ->end()
                        ->integerNode('batch_size')
                            ->info('Set the number of urls to dispatch per message')
                            ->defaultNull()
                            ->validate()
                                ->ifTrue(static fn (int $batchSize): bool => !($batchSize > 0))
                                ->thenInvalid('The batch size must be a number greater than 0.')
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
