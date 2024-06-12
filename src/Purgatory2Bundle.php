<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2;

use Sofascore\PurgatoryBundle2\DependencyInjection\CompilerPass\ControllerClassMapPass;
use Sofascore\PurgatoryBundle2\DependencyInjection\CompilerPass\RegisterPurgerPass;
use Sofascore\PurgatoryBundle2\DependencyInjection\PurgatoryExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class Purgatory2Bundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new ControllerClassMapPass());
        $container->addCompilerPass(new RegisterPurgerPass());
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    /**
     * @TODO Remove this method when changing name to PurgatoryBundle
     */
    public function getContainerExtension(): ?ExtensionInterface
    {
        return ($this->extension ??= new PurgatoryExtension()) ?: null;
    }
}
