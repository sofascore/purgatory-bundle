<?php

declare(strict_types=1);

namespace SofaScore\Purgatory\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

final class PurgatoryExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__).'/Resources/config'));
        $loader->load('services.php');

        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('sofascore.purgatory.purger', $config['purger']);
        $container->setParameter('sofascore.purgatory.host', $config['host']);

        if (!$config['entity_change_listener']) {
            $container->removeDefinition('sofascore.purgatory.entity_change_listener');
        }
    }
}
