<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sofascore\PurgatoryBundle2\Cache\Configuration\CachedConfigurationLoader;
use Sofascore\PurgatoryBundle2\Cache\Configuration\ConfigurationLoader;
use Sofascore\PurgatoryBundle2\Cache\Metadata\ControllerMetadataProvider;
use Sofascore\PurgatoryBundle2\Cache\Metadata\PurgeSubscriptionProvider;
use Sofascore\PurgatoryBundle2\Cache\PropertyResolver\AssociationResolver;
use Sofascore\PurgatoryBundle2\Cache\PropertyResolver\EmbeddableResolver;
use Sofascore\PurgatoryBundle2\Cache\PropertyResolver\MethodResolver;
use Sofascore\PurgatoryBundle2\Cache\PropertyResolver\PropertyResolver;
use Sofascore\PurgatoryBundle2\Cache\TargetResolver\ForGroupsResolver;
use Sofascore\PurgatoryBundle2\Cache\TargetResolver\ForPropertiesResolver;
use Sofascore\PurgatoryBundle2\Command\DebugCommand;
use Sofascore\PurgatoryBundle2\Doctrine\DBAL\Middleware;
use Sofascore\PurgatoryBundle2\Listener\EntityChangeListener;
use Sofascore\PurgatoryBundle2\Purger\AsyncPurger;
use Sofascore\PurgatoryBundle2\Purger\InMemoryPurger;
use Sofascore\PurgatoryBundle2\Purger\Messenger\PurgeMessageHandler;
use Sofascore\PurgatoryBundle2\Purger\NullPurger;
use Sofascore\PurgatoryBundle2\Purger\PurgerInterface;
use Sofascore\PurgatoryBundle2\Purger\SymfonyPurger;
use Sofascore\PurgatoryBundle2\RouteParamValueResolver\CompoundValuesResolver;
use Sofascore\PurgatoryBundle2\RouteParamValueResolver\EnumValuesResolver;
use Sofascore\PurgatoryBundle2\RouteParamValueResolver\PropertyValuesResolver;
use Sofascore\PurgatoryBundle2\RouteParamValueResolver\RawValuesResolver;
use Sofascore\PurgatoryBundle2\RouteProvider\AbstractEntityRouteProvider;
use Sofascore\PurgatoryBundle2\RouteProvider\CreatedEntityRouteProvider;
use Sofascore\PurgatoryBundle2\RouteProvider\PropertyAccess\PurgatoryPropertyAccessor;
use Sofascore\PurgatoryBundle2\RouteProvider\RemovedEntityRouteProvider;
use Sofascore\PurgatoryBundle2\RouteProvider\UpdatedEntityRouteProvider;
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

        ->set('sofascore.purgatory.target_resolver.for_properties', ForPropertiesResolver::class)
            ->tag('purgatory.target_resolver')

        ->set('sofascore.purgatory.target_resolver.for_groups', ForGroupsResolver::class)
            ->tag('purgatory.target_resolver')
            ->args([
                service('property_info.serializer_extractor'),
            ])

        ->set('sofascore.purgatory.purge_subscription_provider', PurgeSubscriptionProvider::class)
            ->args([
                tagged_iterator('purgatory.subscription_resolver'),
                service('sofascore.purgatory.controller_metadata_provider'),
                service('doctrine'),
                tagged_locator('purgatory.target_resolver', defaultIndexMethod: 'for'),
            ])

        ->set('sofascore.purgatory.subscription_resolver.property', PropertyResolver::class)
            ->tag('purgatory.subscription_resolver')

        ->set('sofascore.purgatory.subscription_resolver.method', MethodResolver::class)
            ->tag('purgatory.subscription_resolver')
            ->args([
                tagged_iterator('purgatory.subscription_resolver'),
                service('property_info.reflection_extractor'),
            ])

        ->set('sofascore.purgatory.subscription_resolver.association', AssociationResolver::class)
            ->tag('purgatory.subscription_resolver')
            ->args([
                service('property_info.reflection_extractor'),
            ])

        ->set('sofascore.purgatory.subscription_resolver.embeddable', EmbeddableResolver::class)
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
                service('sofascore.purgatory.expression_language')->nullOnInvalid(),
                tagged_locator('purgatory.route_param_value_resolver', defaultIndexMethod: 'for'),
            ])

        ->set('sofascore.purgatory.route_provider.created_entity', CreatedEntityRouteProvider::class)
            ->parent('sofascore.purgatory.route_provider.abstract')
            ->tag('purgatory.route_provider')

        ->set('sofascore.purgatory.route_provider.removed_entity', RemovedEntityRouteProvider::class)
            ->parent('sofascore.purgatory.route_provider.abstract')
            ->tag('purgatory.route_provider')
            ->arg(3, service('doctrine'))

        ->set('sofascore.purgatory.route_provider.updated_entity', UpdatedEntityRouteProvider::class)
            ->parent('sofascore.purgatory.route_provider.abstract')
            ->tag('purgatory.route_provider')

        ->set('sofascore.purgatory.entity_change_listener', EntityChangeListener::class)
            ->args([
                tagged_iterator('purgatory.route_provider'),
                service('router'),
                service('sofascore.purgatory.purger'),
            ])

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

        ->set('sofascore.purgatory.purger.async', AsyncPurger::class)
            ->decorate('sofascore.purgatory.purger', 'sofascore.purgatory.purger.sync')
            ->args([
                service('messenger.default_bus'),
            ])

        ->set('sofascore.purgatory.purge_message_handler', PurgeMessageHandler::class)
            ->args([
                service('sofascore.purgatory.purger.sync'),
            ])

        ->set('sofascore.purgatory.route_param_value_resolver.compound', CompoundValuesResolver::class)
            ->tag('purgatory.route_param_value_resolver')
            ->args([
                tagged_locator('purgatory.route_param_value_resolver', defaultIndexMethod: 'for'),
            ])

        ->set('sofascore.purgatory.route_param_value_resolver.enum', EnumValuesResolver::class)
            ->tag('purgatory.route_param_value_resolver')

        ->set('sofascore.purgatory.route_param_value_resolver.property', PropertyValuesResolver::class)
            ->tag('purgatory.route_param_value_resolver')
            ->args([
                service('sofascore.purgatory.property_accessor'),
            ])

        ->set('sofascore.purgatory.route_param_value_resolver.raw', RawValuesResolver::class)
            ->tag('purgatory.route_param_value_resolver')

        ->set('sofascore.purgatory.property_accessor', PurgatoryPropertyAccessor::class)
            ->args([
                service('property_accessor'),
            ])

        ->set('sofascore.purgatory.command.purge_on_debug', DebugCommand::class)
            ->args([
                service('sofascore.purgatory.configuration_loader'),
                service('doctrine'),
            ])
            ->tag('console.command')
    ;
};
