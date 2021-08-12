<?php

declare(strict_types=1);

namespace SofaScore\Purgatory\DependencyInjection;

use SofaScore\Purgatory\Purger\PurgerInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Alias;
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

        $this->configurePurgerService($container, $config['purger']);

        if (!$config['entity_change_listener']) {
            $container->removeDefinition('sofascore.purgatory.cache_refresh.entity_change_listener');
        }
    }

    private function configurePurgerService(ContainerBuilder $container, string $serviceId): void
    {
        $webCacheImplementation = $container->getDefinition($serviceId);

        if (!is_a($webCacheImplementation->getClass(), PurgerInterface::class, true)) {
            throw new \LogicException(
                sprintf('The purger service should implement the \'%s\' interface.', PurgerInterface::class)
            );
        }

        $container->setAlias('sofascore.purgatory.purger', new Alias($serviceId, false));
    }
}
