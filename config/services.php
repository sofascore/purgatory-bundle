<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sofascore\PurgatoryBundle\AnnotationReader\AttributeReader;
use Sofascore\PurgatoryBundle\AnnotationReader\Driver\DualDriver;
use Sofascore\PurgatoryBundle\AnnotationReader\Reader;
use Sofascore\PurgatoryBundle\Command\DebugCommand;
use Sofascore\PurgatoryBundle\Listener\EntityChangeListener;
use Sofascore\PurgatoryBundle\Mapping\CacheWarmer\AnnotationsLoaderWarmer;
use Sofascore\PurgatoryBundle\Mapping\Loader\AnnotationsLoader;
use Sofascore\PurgatoryBundle\Mapping\Loader\Configuration;
use Sofascore\PurgatoryBundle\Purgatory;
use Sofascore\PurgatoryBundle\Purger\DefaultPurger;
use Sofascore\PurgatoryBundle\Purger\NullPurger;
use Sofascore\PurgatoryBundle\Purger\SymfonyPurger;

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
            service('sofascore.purgatory.mapping.configuration'),
            service('router'),
            service('controller_resolver'),
            service('sofascore.purgatory.annotation_reader'),
            service('doctrine.orm.entity_manager'),
        ])

        ->set('sofascore.purgatory.mapping.annotation_loader.warmer', AnnotationsLoaderWarmer::class)
        ->args([
            service('sofascore.purgatory.mapping.annotation_loader'),
        ])
        ->tag('kernel.cache_warmer', ['priority' => 0])

        ->set('sofascore.purgatory.annotation_reader', Reader::class)
        ->args([
            service('sofascore.purgatory.annotation_reader.driver.dual'),
        ])

        ->set('sofascore.purgatory.annotation_reader.attribute_reader', AttributeReader::class)

        ->set('sofascore.purgatory.annotation_reader.driver.dual', DualDriver::class)
        ->args([
            service('annotation_reader'),
            service('sofascore.purgatory.annotation_reader.attribute_reader'),
        ])

        ->set('sofascore.purgatory.purgatory', Purgatory::class)
        ->args([
            service('sofascore.purgatory.mapping.annotation_loader'),
            service('property_accessor'),
        ])

        ->set('sofascore.purgatory.entity_change_listener', EntityChangeListener::class)
        ->args([
            service('router'),
            service('sofascore.purgatory.purgatory'),
            service('sofascore.purgatory.purger'),
        ])
        ->tag('doctrine.event_listener', ['event' => 'preRemove'])
        ->tag('doctrine.event_listener', ['event' => 'postPersist'])
        ->tag('doctrine.event_listener', ['event' => 'postUpdate'])
        ->tag('doctrine.event_listener', ['event' => 'postFlush'])

        ->set('sofascore.purgatory.command.debug', DebugCommand::class)
        ->args([
            service('sofascore.purgatory.mapping.annotation_loader'),
            service('router'),
        ])
        ->tag('console.command')
        ->set('sofascore.purgatory.purger.default', DefaultPurger::class)
        ->set('sofascore.purgatory.purger.null', NullPurger::class)
        ->set('sofascore.purgatory.purger.symfony', SymfonyPurger::class)
            ->args(
                [
                    service('http_cache.store'),
                    '%sofascore.purgatory.host%',
                ],
            );
};
