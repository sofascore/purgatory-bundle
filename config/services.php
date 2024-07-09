<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sofascore\PurgatoryBundle2\Cache\Configuration\CachedConfigurationLoader;
use Sofascore\PurgatoryBundle2\Cache\Configuration\ConfigurationLoader;
use Sofascore\PurgatoryBundle2\Cache\PropertyResolver\AssociationResolver;
use Sofascore\PurgatoryBundle2\Cache\PropertyResolver\EmbeddableResolver;
use Sofascore\PurgatoryBundle2\Cache\PropertyResolver\MethodResolver;
use Sofascore\PurgatoryBundle2\Cache\PropertyResolver\PropertyResolver;
use Sofascore\PurgatoryBundle2\Cache\RouteMetadata\AttributeMetadataProvider;
use Sofascore\PurgatoryBundle2\Cache\RouteMetadata\YamlMetadataProvider;
use Sofascore\PurgatoryBundle2\Cache\Subscription\PurgeSubscriptionProvider;
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
use Sofascore\PurgatoryBundle2\RouteParamValueResolver\DynamicValuesResolver;
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

        ->set('sofascore.purgatory2.route_metadata_provider.attribute', AttributeMetadataProvider::class)
            ->tag('purgatory2.route_metadata_provider')
            ->args([
                service('router'),
                [],
                [],
            ])

        ->set('sofascore.purgatory2.route_metadata_provider.yaml', YamlMetadataProvider::class)
            ->tag('purgatory2.route_metadata_provider')
            ->args([
                service('router'),
                [],
            ])

        ->set('sofascore.purgatory2.target_resolver.for_properties', ForPropertiesResolver::class)
            ->tag('purgatory2.target_resolver')

        ->set('sofascore.purgatory2.target_resolver.for_groups', ForGroupsResolver::class)
            ->tag('purgatory2.target_resolver')
            ->args([
                service('property_info.serializer_extractor'),
            ])

        ->set('sofascore.purgatory2.purge_subscription_provider', PurgeSubscriptionProvider::class)
            ->args([
                tagged_iterator('purgatory2.subscription_resolver'),
                tagged_iterator('purgatory2.route_metadata_provider'),
                service('doctrine'),
                tagged_locator('purgatory2.target_resolver', defaultIndexMethod: 'for'),
            ])

        ->set('sofascore.purgatory2.subscription_resolver.property', PropertyResolver::class)
            ->tag('purgatory2.subscription_resolver')
            ->args([
                service('doctrine'),
            ])

        ->set('sofascore.purgatory2.subscription_resolver.method', MethodResolver::class)
            ->tag('purgatory2.subscription_resolver')
            ->args([
                tagged_iterator('purgatory2.subscription_resolver'),
                service('property_info.reflection_extractor'),
            ])

        ->set('sofascore.purgatory2.subscription_resolver.association', AssociationResolver::class)
            ->tag('purgatory2.subscription_resolver')
            ->args([
                service('property_info.reflection_extractor'),
            ])

        ->set('sofascore.purgatory2.subscription_resolver.embeddable', EmbeddableResolver::class)
            ->tag('purgatory2.subscription_resolver')
            ->args([
                service('doctrine'),
            ])

        ->set('sofascore.purgatory2.configuration_loader', ConfigurationLoader::class)
            ->args([
                service('sofascore.purgatory2.purge_subscription_provider'),
            ])

        ->set('sofascore.purgatory2.cached_configuration_loader', CachedConfigurationLoader::class)
            ->tag('kernel.cache_warmer')
            ->decorate('sofascore.purgatory2.configuration_loader')
            ->args([
                service('.inner'),
                service('router'),
                '%kernel.build_dir%',
                '%kernel.debug%',
            ])

        ->set('sofascore.purgatory2.doctrine_middleware', Middleware::class)
            ->args([
                service('sofascore.purgatory2.entity_change_listener'),
            ])

        ->set('sofascore.purgatory2.cache.expression_language')
            ->parent('cache.system')
            ->private()
            ->tag('cache.pool')

        ->set('sofascore.purgatory2.expression_language', ExpressionLanguage::class)
            ->args([
                service('sofascore.purgatory2.cache.expression_language')->nullOnInvalid(),
            ])

        ->set('sofascore.purgatory2.route_provider.abstract', AbstractEntityRouteProvider::class)
            ->abstract()
            ->args([
                service('sofascore.purgatory2.configuration_loader'),
                service('sofascore.purgatory2.expression_language')->nullOnInvalid(),
                tagged_locator('purgatory2.route_param_value_resolver', defaultIndexMethod: 'for'),
            ])

        ->set('sofascore.purgatory2.route_provider.created_entity', CreatedEntityRouteProvider::class)
            ->parent('sofascore.purgatory2.route_provider.abstract')
            ->tag('purgatory2.route_provider')

        ->set('sofascore.purgatory2.route_provider.removed_entity', RemovedEntityRouteProvider::class)
            ->parent('sofascore.purgatory2.route_provider.abstract')
            ->tag('purgatory2.route_provider')
            ->arg(3, service('doctrine'))

        ->set('sofascore.purgatory2.route_provider.updated_entity', UpdatedEntityRouteProvider::class)
            ->parent('sofascore.purgatory2.route_provider.abstract')
            ->tag('purgatory2.route_provider')

        ->set('sofascore.purgatory2.entity_change_listener', EntityChangeListener::class)
            ->args([
                tagged_iterator('purgatory2.route_provider'),
                service('router'),
                service('sofascore.purgatory2.purger'),
            ])

        ->set('sofascore.purgatory2.purger.null', NullPurger::class)
            ->tag('purgatory2.purger', ['alias' => 'null'])

        ->alias('sofascore.purgatory2.purger', 'sofascore.purgatory2.purger.null')
        ->alias(PurgerInterface::class, 'sofascore.purgatory2.purger')

        ->set('sofascore.purgatory2.purger.in_memory', InMemoryPurger::class)
            ->tag('purgatory2.purger', ['alias' => 'in-memory'])

        ->set('sofascore.purgatory2.purger.symfony', SymfonyPurger::class)
            ->tag('purgatory2.purger', ['alias' => 'symfony'])
            ->args([
                service('http_cache.store'),
                '%.sofascore.purgatory2.purger.host%',
            ])

        ->set('sofascore.purgatory2.purger.async', AsyncPurger::class)
            ->decorate('sofascore.purgatory2.purger', 'sofascore.purgatory2.purger.sync')
            ->args([
                service('messenger.default_bus'),
            ])

        ->set('sofascore.purgatory2.purge_message_handler', PurgeMessageHandler::class)
            ->args([
                service('sofascore.purgatory2.purger.sync'),
            ])

        ->set('sofascore.purgatory2.route_param_value_resolver.compound', CompoundValuesResolver::class)
            ->tag('purgatory2.route_param_value_resolver')
            ->args([
                tagged_locator('purgatory2.route_param_value_resolver', defaultIndexMethod: 'for'),
            ])

        ->set('sofascore.purgatory2.route_param_value_resolver.enum', EnumValuesResolver::class)
            ->tag('purgatory2.route_param_value_resolver')

        ->set('sofascore.purgatory2.route_param_value_resolver.property', PropertyValuesResolver::class)
            ->tag('purgatory2.route_param_value_resolver')
            ->args([
                service('sofascore.purgatory2.property_accessor'),
            ])

        ->set('sofascore.purgatory2.route_param_value_resolver.raw', RawValuesResolver::class)
            ->tag('purgatory2.route_param_value_resolver')

        ->set('sofascore.purgatory2.route_parameter_resolver.dynamic', DynamicValuesResolver::class)
            ->tag('purgatory2.route_param_value_resolver')
            ->args([
                tagged_locator('purgatory2.route_parameter_resolver_service', 'alias'),
                service('sofascore.purgatory2.property_accessor'),
            ])

        ->set('sofascore.purgatory2.property_accessor', PurgatoryPropertyAccessor::class)
            ->args([
                service('property_accessor'),
            ])

        ->set('sofascore.purgatory2.command.purge_on_debug', DebugCommand::class)
            ->args([
                service('sofascore.purgatory2.configuration_loader'),
                service('doctrine'),
            ])
            ->tag('console.command')
    ;
};
