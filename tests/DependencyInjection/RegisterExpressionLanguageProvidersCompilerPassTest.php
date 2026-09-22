<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\DependencyInjection\RegisterExpressionLanguageProvidersCompilerPass;
use Sofascore\PurgatoryBundle\Exception\RuntimeException;
use Sofascore\PurgatoryBundle\PurgatoryBundle;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;

#[CoversClass(RegisterExpressionLanguageProvidersCompilerPass::class)]
final class RegisterExpressionLanguageProvidersCompilerPassTest extends TestCase
{
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->container->setParameter('kernel.project_dir', __DIR__);
        $this->container->setParameter('kernel.build_dir', __DIR__);
        $this->container->setParameter('kernel.environment', 'dev');

        (new PurgatoryBundle())->getContainerExtension()->load([], $this->container);
    }

    protected function tearDown(): void
    {
        unset($this->container);
    }

    public function testProcess(): void
    {
        $this->container->register(id: 'foo', class: \stdClass::class)
            ->addTag(
                name: 'purgatory.expression_language_function',
                attributes: ['function' => 'one', 'method' => '__invoke'],
            )
            ->addTag(
                name: 'purgatory.expression_language_function',
                attributes: ['function' => 'two', 'method' => 'someMethod'],
            );
        $this->container->register(id: 'bar', class: \stdClass::class)
            ->addTag(
                name: 'purgatory.expression_language_function',
                attributes: ['function' => 'three', 'method' => 'anotherMethod'],
            );

        $this->container->register(id: 'other_provider', class: \stdClass::class)
            ->addTag('purgatory.expression_language_provider');

        $compilerPass = new RegisterExpressionLanguageProvidersCompilerPass();
        $compilerPass->process($this->container);

        self::assertTrue($this->container->hasDefinition('sofascore.purgatory.expression_language_provider'));
        $definition = $this->container->getDefinition('sofascore.purgatory.expression_language_provider');
        self::assertCount(1, $arguments = $definition->getArguments());

        $definition = $this->container->getDefinition((string) $arguments[0]);
        self::assertSame(ServiceLocator::class, $definition->getClass());

        self::assertCount(1, $arguments = $definition->getArguments());
        /** @var array<string, ServiceClosureArgument> $argument */
        $argument = $arguments[0];
        self::assertIsArray($argument);

        self::assertArrayHasKey('one', $argument);
        self::assertInstanceOf(ServiceClosureArgument::class, $argument['one']);
        /** @var Definition $serviceDefinition */
        $serviceDefinition = $argument['one']->getValues()[0];
        self::assertSame(\Closure::class, $serviceDefinition->getClass());
        self::assertEquals([[new Reference('foo'), '__invoke']], $serviceDefinition->getArguments());
        self::assertSame([\Closure::class, 'fromCallable'], $serviceDefinition->getFactory());

        self::assertArrayHasKey('two', $argument);
        self::assertInstanceOf(ServiceClosureArgument::class, $argument['two']);
        /** @var Definition $serviceDefinition */
        $serviceDefinition = $argument['two']->getValues()[0];
        self::assertSame(\Closure::class, $serviceDefinition->getClass());
        self::assertEquals([[new Reference('foo'), 'someMethod']], $serviceDefinition->getArguments());
        self::assertSame([\Closure::class, 'fromCallable'], $serviceDefinition->getFactory());

        self::assertArrayHasKey('three', $argument);
        self::assertInstanceOf(ServiceClosureArgument::class, $argument['three']);
        /** @var Definition $serviceDefinition */
        $serviceDefinition = $argument['three']->getValues()[0];
        self::assertSame(\Closure::class, $serviceDefinition->getClass());
        self::assertEquals([[new Reference('bar'), 'anotherMethod']], $serviceDefinition->getArguments());
        self::assertSame([\Closure::class, 'fromCallable'], $serviceDefinition->getFactory());

        $definition = $this->container->getDefinition('sofascore.purgatory.expression_language');
        self::assertCount(2, $arguments = $definition->getArguments());
        self::assertEquals([
            new Reference('sofascore.purgatory.expression_language_provider'),
            new Reference('other_provider'),
        ], $arguments[1]);
    }

    public function testExpressionLangProviderIsRemovedWhenThereIsNoExpressionLangService(): void
    {
        $this->container->removeDefinition('sofascore.purgatory.expression_language');

        $compilerPass = new RegisterExpressionLanguageProvidersCompilerPass();
        $compilerPass->process($this->container);

        self::assertFalse($this->container->hasDefinition('sofascore.purgatory.expression_language_provider'));
    }

    public function testExpressionLangProviderIsRemovedWhenThereAreNoFunctions(): void
    {
        $compilerPass = new RegisterExpressionLanguageProvidersCompilerPass();
        $compilerPass->process($this->container);

        self::assertFalse($this->container->hasDefinition('sofascore.purgatory.expression_language_provider'));

        $definition = $this->container->getDefinition('sofascore.purgatory.expression_language');
        self::assertCount(1, $definition->getArguments());
    }

    public function testExceptionIsThrownWhenSameFunctionNameIsUsedMultipleTimes(): void
    {
        $this->container->register(id: 'foo', class: \stdClass::class)->addTag(
            name: 'purgatory.expression_language_function',
            attributes: ['function' => 'one', 'method' => '__invoke'],
        );
        $this->container->register(id: 'bar', class: \stdClass::class)->addTag(
            name: 'purgatory.expression_language_function',
            attributes: ['function' => 'one', 'method' => '__invoke'],
        );

        $compilerPass = new RegisterExpressionLanguageProvidersCompilerPass();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The function name "one" is already used by "foo::__invoke".');

        $compilerPass->process($this->container);
    }
}
