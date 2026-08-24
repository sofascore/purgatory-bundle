<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle;

use Sofascore\PurgatoryBundle\DependencyInjection\CompilerPass\ControllerClassMapPass;
use Sofascore\PurgatoryBundle\DependencyInjection\CompilerPass\RegisterExpressionLanguageProvidersPass;
use Sofascore\PurgatoryBundle\DependencyInjection\CompilerPass\RegisterPurgerPass;
use Sofascore\PurgatoryBundle\DependencyInjection\CompilerPass\RegisterRouteParamServicesPass;
use Sofascore\PurgatoryBundle\DependencyInjection\CompilerPass\ResolveTaggedLocatorIndexPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class PurgatoryBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new ControllerClassMapPass());
        $container->addCompilerPass(new RegisterExpressionLanguageProvidersPass());
        $container->addCompilerPass(new RegisterPurgerPass());
        $container->addCompilerPass(new RegisterRouteParamServicesPass());
        // Runs after all before-optimization passes so that late-added tags are seen,
        // but before ServiceLocatorTagPass which consumes the "for" attribute.
        $container->addCompilerPass(new ResolveTaggedLocatorIndexPass(), PassConfig::TYPE_OPTIMIZE, 1);
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
