<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sofascore\PurgatoryBundle2\DataCollector\PurgatoryDataCollector;
use Sofascore\PurgatoryBundle2\Purger\TraceablePurger;
use Symfony\Component\DependencyInjection\ContainerInterface;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->defaults()
            ->private()

        ->set('sofascore.purgatory2.data_collector', PurgatoryDataCollector::class)
            ->tag('data_collector', [
                'id' => 'purgatory',
                'template' => '@Purgatory2/profiler.html.twig',
            ])
            ->args([
                param('.sofascore.purgatory2.purger.name'),
                param('.sofascore.purgatory2.purger.async_transport'),
            ])

        ->set('sofascore.purgatory2.purger.traceable', TraceablePurger::class)
            ->decorate('sofascore.purgatory2.purger', priority: -1000)
            ->args([
                service('.inner'),
                service('sofascore.purgatory2.data_collector'),
            ])

        ->set('sofascore.purgatory2.purger.sync.traceable', TraceablePurger::class)
            ->decorate('sofascore.purgatory2.purger.sync', priority: -1000, invalidBehavior: ContainerInterface::IGNORE_ON_INVALID_REFERENCE)
            ->args([
                service('.inner'),
                service('sofascore.purgatory2.data_collector'),
            ])
    ;
};
