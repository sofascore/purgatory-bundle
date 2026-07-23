<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle;

use Sofascore\PurgatoryBundle\DependencyInjection\ControllerClassMapCompilerPass;
use Sofascore\PurgatoryBundle\DependencyInjection\RegisterExpressionLanguageProvidersCompilerPass;
use Sofascore\PurgatoryBundle\DependencyInjection\RegisterPurgerCompilerPass;
use Sofascore\PurgatoryBundle\DependencyInjection\RegisterRouteParamServicesCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class PurgatoryBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new ControllerClassMapCompilerPass());
        $container->addCompilerPass(new RegisterExpressionLanguageProvidersCompilerPass());
        $container->addCompilerPass(new RegisterPurgerCompilerPass());
        $container->addCompilerPass(new RegisterRouteParamServicesCompilerPass());
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
