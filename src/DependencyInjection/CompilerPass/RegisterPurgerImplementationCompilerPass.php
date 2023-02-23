<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\DependencyInjection\CompilerPass;

use Sofascore\PurgatoryBundle\Purger\PurgerInterface;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class RegisterPurgerImplementationCompilerPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container): void
    {
        /** @var string $serviceId */
        $serviceId = $container->getParameter('sofascore.purgatory.purger');

        // Don't pollute the container with unnecessary metadata.
        $container->getParameterBag()->remove('sofascore.purgatory.purger');

        $purgerImplementation = $container->findDefinition($serviceId);

        if (!is_a($purgerImplementation->getClass(), PurgerInterface::class, true)) {
            throw new \LogicException(
                sprintf('The purger service should implement the \'%s\' interface.', PurgerInterface::class),
            );
        }

        $container->setAlias('sofascore.purgatory.purger', new Alias($serviceId, false));
    }
}
