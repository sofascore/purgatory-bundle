<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sofascore\PurgatoryBundle\DataCollector\PurgatoryDataCollector;
use Sofascore\PurgatoryBundle\Purger\TraceablePurger;
use Symfony\Component\DependencyInjection\ContainerInterface;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->defaults()
            ->private()

        ->set('sofascore.purgatory.data_collector', PurgatoryDataCollector::class)
            ->tag('data_collector', [
                'id' => 'purgatory',
                'template' => '@Purgatory/profiler.html.twig',
            ])
            ->args([
                param('.sofascore.purgatory.purger.name'),
                param('.sofascore.purgatory.purger.async_transport'),
            ])

        ->set('sofascore.purgatory.purger.traceable', TraceablePurger::class)
            ->decorate('sofascore.purgatory.purger', priority: -1000)
            ->args([
                service('.inner'),
                service('sofascore.purgatory.data_collector'),
            ])

        ->set('sofascore.purgatory.purger.sync.traceable', TraceablePurger::class)
            ->decorate('sofascore.purgatory.purger.sync', priority: -1000, invalidBehavior: ContainerInterface::IGNORE_ON_INVALID_REFERENCE)
            ->args([
                service('.inner'),
                service('sofascore.purgatory.data_collector'),
            ])
    ;
};
