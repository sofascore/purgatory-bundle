<?php

namespace SofaScore\Purgatory;

use SofaScore\Purgatory\DependencyInjection\CompilerPass\RegisterPurgerImplementationCompilerPass;
use SofaScore\Purgatory\DependencyInjection\CompilerPass\SymfonyPurgerRemovalCompilerPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
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
    }
}
