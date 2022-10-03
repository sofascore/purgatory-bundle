<?php

declare(strict_types=1);

namespace SofaScore\Purgatory\DependencyInjection\CompilerPass;

use SofaScore\Purgatory\Purger\PurgerInterface;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

final class RegisterPurgerImplementationCompilerPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container): void
    {
        $serviceId = $container->getParameter('sofascore.purgatory.purger');
        assert(is_string($serviceId));

        // Don't pollute the container with unnecessary metadata.
        $container->getParameterBag()->remove('sofascore.purgatory.purger');

        $purgerImplementation = $container->findDefinition($serviceId);

        if (!is_a($purgerImplementation->getClass(), PurgerInterface::class, true)) {
            throw new \LogicException(
                sprintf('The purger service should implement the \'%s\' interface.', PurgerInterface::class)
            );
        }

        $container->setAlias('sofascore.purgatory.purger', new Alias($serviceId, false));
    }
}
