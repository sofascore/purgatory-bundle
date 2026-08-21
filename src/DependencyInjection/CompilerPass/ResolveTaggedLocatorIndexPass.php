<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\DependencyInjection\CompilerPass;

use Sofascore\PurgatoryBundle\Attribute\Target\TargetInterface;
use Sofascore\PurgatoryBundle\Cache\TargetResolver\TargetResolverInterface;
use Sofascore\PurgatoryBundle\Exception\RuntimeException;
use Sofascore\PurgatoryBundle\RouteParamValueResolver\ValuesResolverInterface;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Sets the "for" tag attribute used as the service locator index from the tagged
 * class's static "for()" method, replacing the "defaultIndexMethod" reflection
 * fallback which is deprecated since Symfony 8.1.
 */
final class ResolveTaggedLocatorIndexPass implements CompilerPassInterface
{
    private const TAG_TO_INTERFACE = [
        'purgatory.target_resolver' => TargetResolverInterface::class,
        'purgatory.route_param_value_resolver' => ValuesResolverInterface::class,
    ];

    public function process(ContainerBuilder $container): void
    {
        foreach (self::TAG_TO_INTERFACE as $tagName => $interface) {
            /** @var list<array<string, mixed>> $tags */
            foreach ($container->findTaggedServiceIds($tagName, true) as $id => $tags) {
                if (!array_any($tags, static fn (array $attributes): bool => !isset($attributes['for']))) {
                    continue;
                }

                $definition = $container->getDefinition($id);
                $class = $definition->getClass();

                $parent = $definition;
                while (null === $class && $parent instanceof ChildDefinition) {
                    $parent = $container->findDefinition($parent->getParent());
                    $class = $parent->getClass();
                }

                /** @var ?class-string<TargetResolverInterface<TargetInterface>|ValuesResolverInterface<array<mixed>>> $class */
                $class = $container->getParameterBag()->resolveValue($class);

                if (!\is_string($class)) {
                    throw new RuntimeException(\sprintf('The class of the service "%s" tagged with "%s" could not be determined.', $id, $tagName));
                }

                if (!is_a($class, $interface, true)) {
                    throw new RuntimeException(\sprintf('The class "%s" of the service "%s" tagged with "%s" must implement "%s".', $class, $id, $tagName, $interface));
                }

                $for = $class::for();

                $definition->clearTag($tagName);
                foreach ($tags as $attributes) {
                    $definition->addTag($tagName, $attributes + ['for' => $for]);
                }
            }
        }
    }
}
