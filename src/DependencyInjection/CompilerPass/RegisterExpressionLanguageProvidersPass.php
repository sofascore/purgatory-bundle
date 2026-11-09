<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\DependencyInjection\CompilerPass;

use Sofascore\PurgatoryBundle\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Kernel;

final class RegisterExpressionLanguageProvidersPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has('sofascore.purgatory.expression_language')) {
            $container->removeDefinition('sofascore.purgatory.expression_language_provider');

            return;
        }

        $functionReferences = [];
        $usedFunctionNames = [];
        /** @var list<array{function: string, method?: string}> $attributes */
        foreach ($container->findTaggedServiceIds('purgatory.expression_language_function', true) as $id => $attributes) {
            foreach ($attributes as $attribute) {
                $function = $attribute['function'];
                $method = $attribute['method'] ?? '__invoke';

                if (isset($usedFunctionNames[$function])) {
                    throw new RuntimeException(\sprintf('The function name "%s" is already used by "%s".', $function, $usedFunctionNames[$function]));
                }

                if (null !== ($class = $container->getDefinition($id)->getClass())
                    && ($reflectionClass = $container->getReflectionClass($class, false))
                    && !$reflectionClass->hasMethod($method)
                ) {
                    throw new RuntimeException(\sprintf('Invalid expression language function, the method "%s::%s()" does not exist.', $reflectionClass->name, $method));
                }

                $functionReferences[$function] = (new Definition(\Closure::class, [[new Reference($id), $method]]))
                    ->setFactory([\Closure::class, 'fromCallable']);

                if (Kernel::MAJOR_VERSION <= 5) {
                    $container->setDefinition($factoryId = $id.'.'.$method.'.factory', $functionReferences[$function]);
                    $functionReferences[$function] = new Reference($factoryId);
                }

                $usedFunctionNames[$function] = \sprintf('%s::%s', $id, $method);
            }
        }

        if ($functionReferences) {
            $container->getDefinition('sofascore.purgatory.expression_language_provider')
                ->replaceArgument(0, ServiceLocatorTagPass::register($container, $functionReferences));
        } else {
            $container->removeDefinition('sofascore.purgatory.expression_language_provider');
        }

        $providerReferences = [];
        foreach ($container->findTaggedServiceIds('purgatory.expression_language_provider', true) as $id => $_) {
            $providerReferences[] = new Reference($id);
        }

        if ($providerReferences) {
            $container->getDefinition('sofascore.purgatory.expression_language')->setArgument(1, $providerReferences);
        }
    }
}
