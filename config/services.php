<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Doctrine\ORM\Events as DoctrineEvents;
use Sofascore\PurgatoryBundle2\Cache\Configuration\CachedConfigurationLoader;
use Sofascore\PurgatoryBundle2\Cache\Configuration\ConfigurationLoader;
use Sofascore\PurgatoryBundle2\Cache\Metadata\ControllerMetadataProvider;
use Sofascore\PurgatoryBundle2\Cache\Metadata\PurgeSubscriptionProvider;
use Sofascore\PurgatoryBundle2\Cache\PropertyResolver\AssociationResolver;
use Sofascore\PurgatoryBundle2\Cache\PropertyResolver\EmbeddableResolver;
use Sofascore\PurgatoryBundle2\Cache\PropertyResolver\MethodResolver;
use Sofascore\PurgatoryBundle2\Cache\PropertyResolver\PropertyResolver;
use Sofascore\PurgatoryBundle2\Doctrine\DBAL\Middleware;
use Sofascore\PurgatoryBundle2\Listener\EntityChangeListener;
use Sofascore\PurgatoryBundle2\Purger\InMemoryPurger;
use Sofascore\PurgatoryBundle2\Purger\NullPurger;
use Sofascore\PurgatoryBundle2\Purger\PurgerInterface;
use Sofascore\PurgatoryBundle2\Purger\SymfonyPurger;
use Sofascore\PurgatoryBundle2\RouteProvider\AbstractEntityRouteProvider;
use Sofascore\PurgatoryBundle2\RouteProvider\CreatedEntityRouteProvider;
use Sofascore\PurgatoryBundle2\RouteProvider\RemovedEntityRouteProvider;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->defaults()
            ->private()

        ->set('sofascore.purgatory.controller_metadata_provider', ControllerMetadataProvider::class)
            ->args([
                service('router'),
                [],
                [],
            ])

        ->set('sofascore.purgatory.purge_subscription_provider', PurgeSubscriptionProvider::class)
            ->args([
                tagged_iterator('purgatory.subscription_resolver'),
                service('sofascore.purgatory.controller_metadata_provider'),
                service('doctrine'),
            ])

        ->set('sofascore.purgatory.field_resolver', PropertyResolver::class)
            ->tag('purgatory.subscription_resolver')

        ->set('sofascore.purgatory.method_resolver', MethodResolver::class)
            ->tag('purgatory.subscription_resolver')
            ->args([
                tagged_iterator('purgatory.subscription_resolver'),
                service('property_info.reflection_extractor'),
            ])

        ->set('sofascore.purgatory.association_resolver', AssociationResolver::class)
            ->tag('purgatory.subscription_resolver')
            ->args([
                service('property_info.reflection_extractor'),
            ])

        ->set('sofascore.purgatory.embeddable_resolver', EmbeddableResolver::class)
            ->tag('purgatory.subscription_resolver')
            ->args([
                service('doctrine'),
            ])

        ->set('sofascore.purgatory.configuration_loader', ConfigurationLoader::class)
            ->args([
                service('sofascore.purgatory.purge_subscription_provider'),
            ])

        ->set('sofascore.purgatory.cached_configuration_loader', CachedConfigurationLoader::class)
            ->tag('kernel.cache_warmer')
            ->decorate('sofascore.purgatory.configuration_loader')
            ->args([
                service('.inner'),
                service('router'),
                '%kernel.build_dir%',
                '%kernel.debug%',
            ])

        ->set('sofascore.purgatory.doctrine_middleware', Middleware::class)
            ->args([
                service('sofascore.purgatory.entity_change_listener'),
            ])

        ->set('sofascore.purgatory.cache.expression_language')
            ->parent('cache.system')
            ->private()
            ->tag('cache.pool')

        ->set('sofascore.purgatory.expression_language', ExpressionLanguage::class)
            ->args([
                service('sofascore.purgatory.cache.expression_language')->nullOnInvalid(),
            ])

        ->set('sofascore.purgatory.route_provider.abstract', AbstractEntityRouteProvider::class)
            ->abstract()
            ->args([
                service('sofascore.purgatory.configuration_loader'),
                service('property_accessor'),
                service('sofascore.purgatory.expression_language')->nullOnInvalid(),
            ])

        ->set('sofascore.purgatory.route_provider.created_entity', CreatedEntityRouteProvider::class)
            ->parent('sofascore.purgatory.route_provider.abstract')
            ->tag('purgatory.route_provider')

        ->set('sofascore.purgatory.route_provider.removed_entity', RemovedEntityRouteProvider::class)
            ->parent('sofascore.purgatory.route_provider.abstract')
            ->tag('purgatory.route_provider')
            ->arg(3, service('doctrine'))

        ->set('sofascore.purgatory.entity_change_listener', EntityChangeListener::class)
            ->args([
                tagged_iterator('purgatory.route_provider'),
                service('router'),
                service('sofascore.purgatory.purger'),
            ])
            ->tag('doctrine.event_listener', ['event' => DoctrineEvents::preRemove])
            ->tag('doctrine.event_listener', ['event' => DoctrineEvents::postPersist])
            ->tag('doctrine.event_listener', ['event' => DoctrineEvents::postUpdate])

        ->set('sofascore.purgatory.purger.null', NullPurger::class)
            ->tag('purgatory.purger', ['alias' => 'null'])

        ->alias('sofascore.purgatory.purger', 'sofascore.purgatory.purger.null')
        ->alias(PurgerInterface::class, 'sofascore.purgatory.purger')

        ->set('sofascore.purgatory.purger.in_memory', InMemoryPurger::class)
            ->tag('purgatory.purger', ['alias' => 'in-memory'])

        ->set('sofascore.purgatory.purger.symfony', SymfonyPurger::class)
            ->tag('purgatory.purger', ['alias' => 'symfony'])
            ->args([
                service('http_cache.store'),
                '%.sofascore.purgatory.purger.host%',
            ])
    ;
};
