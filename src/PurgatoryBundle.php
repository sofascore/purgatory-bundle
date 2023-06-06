<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle;

use Sofascore\PurgatoryBundle\DependencyInjection\CompilerPass\ControllerClassMapPass;
use Sofascore\PurgatoryBundle\DependencyInjection\CompilerPass\RegisterPurgerImplementationCompilerPass;
use Sofascore\PurgatoryBundle\DependencyInjection\CompilerPass\SymfonyPurgerRemovalCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class PurgatoryBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new RegisterPurgerImplementationCompilerPass());
        $container->addCompilerPass(new SymfonyPurgerRemovalCompilerPass());
        $container->addCompilerPass(new ControllerClassMapPass());
    }
}
