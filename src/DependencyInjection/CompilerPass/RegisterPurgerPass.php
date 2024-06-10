<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\DependencyInjection\CompilerPass;

use Sofascore\PurgatoryBundle2\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class RegisterPurgerPass implements CompilerPassInterface
{
    /**
     * {@inheritDoc}
     */
    public function process(ContainerBuilder $container): void
    {
        /** @var ?string $purgerAlias */
        $purgerAlias = $container->getParameter('.sofascore.purgatory.purger.name');

        if (null !== $purgerAlias) {
            $this->setPurger($container, $purgerAlias);
        } elseif ($container->has('http_cache.store')) {
            $container->setAlias('sofascore.purgatory.purger', 'sofascore.purgatory.purger.symfony');
        }

        if (!$container->has('http_cache.store')) {
            $container->removeDefinition('sofascore.purgatory.purger.symfony');
        }
    }

    private function setPurger(ContainerBuilder $container, string $purgerAlias): void
    {
        /** @var list<array{alias?: string}> $tags */
        foreach ($container->findTaggedServiceIds('purgatory.purge_route_generator') as $id => $tags) {
            foreach ($tags as $tag) {
                if (isset($tag['alias']) && $tag['alias'] === $purgerAlias) {
                    $container->setAlias('sofascore.purgatory.purger', $id);

                    return;
                }
            }
        }

        if (!$container->has($purgerAlias)) {
            throw new RuntimeException(sprintf('The configured purger service "%s" does not exist.', $purgerAlias));
        }

        $container->setAlias('sofascore.purgatory.purger', $purgerAlias);
    }
}
