<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sofascore\PurgatoryBundle2\Cache\Metadata\ControllerMetadataProvider;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('sofascore.purgatory.controller_metadata_provider', ControllerMetadataProvider::class)
        ->args([
            service('router'),
            [],
        ])
    ;
};
