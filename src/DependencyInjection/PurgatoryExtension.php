<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\DependencyInjection;

use Sofascore\PurgatoryBundle2\Attribute\PurgeOn;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

final class PurgatoryExtension extends ConfigurableExtension
{
    protected function loadInternal(array $mergedConfig, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__, 2).'/config'));
        $loader->load('services.php');

        $container->registerAttributeForAutoconfiguration(
            PurgeOn::class,
            static function (ChildDefinition $definition, PurgeOn $attribute, \ReflectionMethod $reflectionMethod): void {
                $definition->addTag(
                    name: 'purgatory.purge_on',
                    attributes: [
                        'method' => $reflectionMethod->getName(),
                    ],
                );
            },
        );
    }

    public function getAlias(): string
    {
        return 'sofascore_purgatory';
    }
}
