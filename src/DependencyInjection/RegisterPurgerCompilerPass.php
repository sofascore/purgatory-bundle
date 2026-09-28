<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\DependencyInjection;

use Sofascore\PurgatoryBundle\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class RegisterPurgerCompilerPass implements CompilerPassInterface
{
    /**
     * {@inheritDoc}
     */
    public function process(ContainerBuilder $container): void
    {
        /** @var ?string $purgerAlias */
        $purgerAlias = $container->getParameter('.sofascore.purgatory.purger.name');

        $symfonyPurgerIsAvailable = $container->has('http_cache.store');

        if (null !== $purgerAlias) {
            $container->setAlias('sofascore.purgatory.purger', $purgerId = $this->resolvePurgerId($container, $purgerAlias));
        } elseif ($symfonyPurgerIsAvailable) {
            $container->setAlias('sofascore.purgatory.purger', $purgerId = 'sofascore.purgatory.purger.symfony');
            $container->setParameter('.sofascore.purgatory.purger.name', 'symfony');
        } else {
            $purgerId = 'sofascore.purgatory.purger.void';
            $container->setParameter('.sofascore.purgatory.purger.name', 'void');
        }

        if ('sofascore.purgatory.purger.chain' === $purgerId) {
            $this->setPurgerChain($container);
        } else {
            $container->removeDefinition('sofascore.purgatory.purger.chain');
        }

        if (!$symfonyPurgerIsAvailable) {
            $container->removeDefinition('sofascore.purgatory.purger.symfony');
        }
    }

    private function setPurgerChain(ContainerBuilder $container): void
    {
        /** @var list<array{name: string, hosts: list<string>, http_client: ?string}> $purgerChain */
        $purgerChain = $container->getParameter('.sofascore.purgatory.purger.chain');

        $purgers = [];
        foreach ($purgerChain as $index => $purgerConfig) {
            $purgerId = $this->resolvePurgerId($container, $purgerConfig['name']);

            if ('sofascore.purgatory.purger.chain' === $purgerId) {
                throw new RuntimeException('The chain purger cannot be part of the purger chain.');
            }

            if ($purgerConfig['hosts'] || null !== $purgerConfig['http_client']) {
                if ('sofascore.purgatory.purger.varnish' !== $purgerId) {
                    throw new RuntimeException(\sprintf('The "hosts" and "http_client" options in the purger chain can only be used with the Varnish purger, but they were set for "%s".', $purgerConfig['name']));
                }

                $definition = (new ChildDefinition($purgerId))
                    ->replaceArgument(1, $purgerConfig['hosts']);

                if (null !== $purgerConfig['http_client']) {
                    $definition->replaceArgument(0, new Reference($purgerConfig['http_client']));
                }

                $container->setDefinition($purgerId = 'sofascore.purgatory.purger.chain.'.$index, $definition);
            }

            $purgers[] = new Reference($purgerId);
        }

        if (!$purgers) {
            throw new RuntimeException('The chain purger requires at least one purger to be defined in the "chain" option.');
        }

        $container->getDefinition('sofascore.purgatory.purger.chain')
            ->replaceArgument(0, new IteratorArgument($purgers));
    }

    private function resolvePurgerId(ContainerBuilder $container, string $purgerAlias): string
    {
        /** @var list<array{alias?: string}> $tags */
        foreach ($container->findTaggedServiceIds('purgatory.purger') as $id => $tags) {
            foreach ($tags as $tag) {
                if (isset($tag['alias']) && $tag['alias'] === $purgerAlias) {
                    return $id;
                }
            }
        }

        if (!$container->has($purgerAlias)) {
            throw new RuntimeException(\sprintf('The configured purger service "%s" does not exist.', $purgerAlias));
        }

        return $purgerAlias;
    }
}
