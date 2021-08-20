<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use SofaScore\Purgatory\AnnotationReader\AttributeReader;
use SofaScore\Purgatory\AnnotationReader\Driver\DualDriver;
use SofaScore\Purgatory\AnnotationReader\Reader;
use SofaScore\Purgatory\CacheRefresh;
use SofaScore\Purgatory\Command\DebugCommand;
use SofaScore\Purgatory\Listener\EntityChangeListener;
use SofaScore\Purgatory\Mapping\CacheWarmer\AnnotationsLoaderWarmer;
use SofaScore\Purgatory\Mapping\Loader\AnnotationsLoader;
use SofaScore\Purgatory\Mapping\Loader\Configuration;

return static function (ContainerConfigurator $container) {
    $container->parameters()
        ->set('sofascore.purgatory.route_ignore_patterns', [])
    ;

    $container->services()
        ->set('sofascore.purgatory.mapping.configuration', Configuration::class)
        ->args([
            '%kernel.environment%',
            '%kernel.debug%',
        ])
        ->call('setCacheDir', ['%kernel.cache_dir%'])
        ->call('setRouteIgnorePatterns', ['%sofascore.purgatory.route_ignore_patterns%'])

        ->set('sofascore.purgatory.mapping.annotation_loader', AnnotationsLoader::class)
        ->args([
            ref('sofascore.purgatory.mapping.configuration'),
            ref('router'),
            ref('controller_resolver'),
            ref('sofascore.purgatory.annotation_reader'),
            ref('doctrine.orm.entity_manager'),
        ])

        ->set('sofascore.purgatory.mapping.annotation_loader.warmer', AnnotationsLoaderWarmer::class)
        ->args([
            ref('sofascore.purgatory.mapping.annotation_loader'),
        ])
        ->tag('kernel.cache_warmer', ['priority' => 0])

        ->set('sofascore.purgatory.annotation_reader', Reader::class)
        ->args([
            ref('sofascore.purgatory.annotation_reader.driver.dual'),
        ])

        ->set('sofascore.purgatory.annotation_reader.attribute_reader', AttributeReader::class)

        ->set('sofascore.purgatory.annotation_reader.driver.dual', DualDriver::class)
        ->args([
            ref('annotation_reader'),
            ref('sofascore.purgatory.annotation_reader.attribute_reader')
        ])

        ->set('sofascore.purgatory.cache_refresh', CacheRefresh::class)
        ->args([
            ref('sofascore.purgatory.mapping.annotation_loader'),
            ref('property_accessor'),
        ])

        ->set('sofascore.purgatory.entity_change_listener', EntityChangeListener::class)
        ->args([
            ref('router'),
            ref('sofascore.purgatory.cache_refresh'),
            ref('sofascore.purgatory.purger'),
        ])
        ->tag('doctrine.event_listener', ['event' => 'preRemove'])
        ->tag('doctrine.event_listener', ['event' => 'postPersist'])
        ->tag('doctrine.event_listener', ['event' => 'postUpdate'])
        ->tag('doctrine.event_listener', ['event' => 'postFlush'])

        ->set('sofascore.purgatory.command.debug', DebugCommand::class)
        ->args([
            ref('sofascore.purgatory.mapping.annotation_loader'),
            ref('router')
        ])
        ->tag('console.command')
    ;
};
