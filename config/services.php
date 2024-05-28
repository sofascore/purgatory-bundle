<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sofascore\PurgatoryBundle2\Cache\Metadata\ControllerMetadataProvider;
use Sofascore\PurgatoryBundle2\Cache\Metadata\PurgeSubscriptionProvider;
use Sofascore\PurgatoryBundle2\Cache\PropertyResolver\EmbeddableResolver;
use Sofascore\PurgatoryBundle2\Cache\PropertyResolver\MethodResolver;
use Sofascore\PurgatoryBundle2\Cache\PropertyResolver\PropertyResolver;

return static function (ContainerConfigurator $container) {
    $container->services()
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
    ;
};
