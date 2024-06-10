<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Doctrine\ORM\Events as DoctrineEvents;
use Sofascore\PurgatoryBundle2\Cache\Configuration\CachedConfigurationLoader;
use Sofascore\PurgatoryBundle2\Cache\Configuration\ConfigurationLoader;
use Sofascore\PurgatoryBundle2\Cache\Metadata\ControllerMetadataProvider;
use Sofascore\PurgatoryBundle2\Cache\Metadata\PurgeSubscriptionProvider;
use Sofascore\PurgatoryBundle2\Cache\PropertyResolver\EmbeddableResolver;
use Sofascore\PurgatoryBundle2\Cache\PropertyResolver\MethodResolver;
use Sofascore\PurgatoryBundle2\Cache\PropertyResolver\PropertyResolver;
use Sofascore\PurgatoryBundle2\Listener\EntityChangeListener;
use Sofascore\PurgatoryBundle2\PurgeRouteGenerator\AbstractEntityRouteGenerator;
use Sofascore\PurgatoryBundle2\PurgeRouteGenerator\RemovedEntityRouteGenerator;
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

        ->set('sofascore.purgatory.embeddable_resolver', EmbeddableResolver::class)
            ->args([
                service('doctrine'),
            ])

        ->set('sofascore.purgatory.field_resolver', PropertyResolver::class)

        ->set('sofascore.purgatory.method_resolver', MethodResolver::class)
            ->args([
                tagged_iterator('purgatory.subscription_resolver'),
                service('property_info.reflection_extractor'),
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

        ->set('sofascore.purgatory.cache.expression_language')
            ->parent('cache.system')
            ->private()
            ->tag('cache.pool')

        ->set('sofascore.purgatory.expression_language', ExpressionLanguage::class)
            ->args([
                service('sofascore.purgatory.cache.expression_language')->nullOnInvalid(),
            ])

        ->set('sofascore.purgatory.purge_route_generator.abstract', AbstractEntityRouteGenerator::class)
            ->abstract()
            ->args([
                service('sofascore.purgatory.configuration_loader'),
                service('property_accessor'),
                service('sofascore.purgatory.expression_language')->nullOnInvalid(),
            ])

        ->set('sofascore.purgatory.purge_route_generator.removed_entity', RemovedEntityRouteGenerator::class)
            ->parent('sofascore.purgatory.purge_route_generator.abstract')
            ->arg(3, service('doctrine'))

        ->set('sofascore.purgatory.entity_change_listener', EntityChangeListener::class)
            ->args([
                tagged_iterator('purgatory.purge_route_generator'),
                service('router'),
            ])
            ->tag('doctrine.event_listener', ['event' => DoctrineEvents::preRemove])
            ->tag('doctrine.event_listener', ['event' => DoctrineEvents::postPersist])
            ->tag('doctrine.event_listener', ['event' => DoctrineEvents::postUpdate])
            ->tag('doctrine.event_listener', ['event' => DoctrineEvents::postFlush])
    ;
};
