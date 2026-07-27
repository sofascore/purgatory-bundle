<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\DependencyInjection;

use Sofascore\PurgatoryBundle\PurgatoryBundle;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;

/**
 * @internal
 *
 * @see https://github.com/symfony/symfony/pull/64955
 */
final class BundleExtensionWrapper extends Extension implements PrependExtensionInterface
{
    public function __construct(
        private readonly PurgatoryBundle $bundle,
        private readonly ExtensionInterface&ConfigurationExtensionInterface&PrependExtensionInterface $extension,
    ) {
    }

    /**
     * @param array<array-key, mixed> $config
     */
    public function getConfiguration(array $config, ContainerBuilder $container): ?ConfigurationInterface
    {
        return $this->extension->getConfiguration($config, $container);
    }

    public function getAlias(): string
    {
        return $this->extension->getAlias();
    }

    public function prepend(ContainerBuilder $container): void
    {
        $this->extension->prepend($container);
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $this->extension->load($configs, $container);
    }

    public function getNamespace(): string
    {
        return $this->bundle->getXMLNamespace();
    }
}
