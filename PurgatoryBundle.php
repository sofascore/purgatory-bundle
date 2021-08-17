<?php

namespace SofaScore\Purgatory;

use SofaScore\Purgatory\DependencyInjection\CompilerPass\RegisterPurgerImplementationCompilerPass;
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
    }
}
