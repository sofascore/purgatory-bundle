<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2;

use Sofascore\PurgatoryBundle2\DependencyInjection\CompilerPass\PurgeOnMethodsCompilerPass;
use Sofascore\PurgatoryBundle2\DependencyInjection\PurgatoryExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class Purgatory2Bundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new PurgeOnMethodsCompilerPass());
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        return $this->extension ??= new PurgatoryExtension();
    }
}
