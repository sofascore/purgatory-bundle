<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\DependencyInjection\CompilerPass\RegisterRouteParamServicesPass;
use Sofascore\PurgatoryBundle\DependencyInjection\PurgatoryExtension;
use Sofascore\PurgatoryBundle\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpKernel\Kernel;

#[CoversClass(RegisterRouteParamServicesPass::class)]
final class RegisterRouteParamServicesPassTest extends TestCase
{
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->container->setParameter('kernel.project_dir', __DIR__);
        (new PurgatoryExtension())->load([], $this->container);
    }

    protected function tearDown(): void
    {
        unset($this->container);
    }

    public function testProcess(): void
    {
        $this->container->register(id: 'foo', class: \stdClass::class)
            ->addTag(
                name: 'purgatory.route_parameter_service',
                attributes: ['alias' => 'one', 'method' => '__invoke'],
            )
            ->addTag(
                name: 'purgatory.route_parameter_service',
                attributes: ['alias' => 'two', 'method' => 'someMethod'],
            );
        $this->container->register(id: 'bar', class: \stdClass::class)
            ->addTag(
                name: 'purgatory.route_parameter_service',
                attributes: ['alias' => 'three', 'method' => 'anotherMethod'],
            );

        $compilerPass = new RegisterRouteParamServicesPass();
        $compilerPass->process($this->container);

        self::assertTrue($this->container->hasDefinition('sofascore.purgatory.route_parameter_resolver.dynamic'));
        $definition = $this->container->getDefinition('sofascore.purgatory.route_parameter_resolver.dynamic');
        self::assertCount(2, $arguments = $definition->getArguments());

        $definition = $this->container->getDefinition((string) $arguments[0]);
        self::assertSame(ServiceLocator::class, $definition->getClass());

        self::assertCount(1, $arguments = $definition->getArguments());
        /** @var array<string, ServiceClosureArgument> $argument */
        $argument = $arguments[0];
        self::assertIsArray($argument);

        self::assertArrayHasKey('one', $argument);
        self::assertInstanceOf(ServiceClosureArgument::class, $argument['one']);
        /** @var Definition $serviceDefinition */
        $serviceDefinition = Kernel::MAJOR_VERSION > 5
            ? $argument['one']->getValues()[0]
            : $this->container->getDefinition((string) $argument['one']->getValues()[0]);
        self::assertSame(\Closure::class, $serviceDefinition->getClass());
        self::assertEquals([[new Reference('foo'), '__invoke']], $serviceDefinition->getArguments());
        self::assertSame([\Closure::class, 'fromCallable'], $serviceDefinition->getFactory());

        self::assertArrayHasKey('two', $argument);
        self::assertInstanceOf(ServiceClosureArgument::class, $argument['two']);
        /** @var Definition $serviceDefinition */
        $serviceDefinition = Kernel::MAJOR_VERSION > 5
            ? $argument['two']->getValues()[0]
            : $this->container->getDefinition((string) $argument['two']->getValues()[0]);
        self::assertSame(\Closure::class, $serviceDefinition->getClass());
        self::assertEquals([[new Reference('foo'), 'someMethod']], $serviceDefinition->getArguments());
        self::assertSame([\Closure::class, 'fromCallable'], $serviceDefinition->getFactory());

        self::assertArrayHasKey('three', $argument);
        self::assertInstanceOf(ServiceClosureArgument::class, $argument['three']);
        /** @var Definition $serviceDefinition */
        $serviceDefinition = Kernel::MAJOR_VERSION > 5
            ? $argument['three']->getValues()[0]
            : $this->container->getDefinition((string) $argument['three']->getValues()[0]);
        self::assertSame(\Closure::class, $serviceDefinition->getClass());
        self::assertEquals([[new Reference('bar'), 'anotherMethod']], $serviceDefinition->getArguments());
        self::assertSame([\Closure::class, 'fromCallable'], $serviceDefinition->getFactory());
    }

    public function testDynamicResolverIsRemovedWhenThereAreNoServices(): void
    {
        $compilerPass = new RegisterRouteParamServicesPass();
        $compilerPass->process($this->container);

        self::assertFalse($this->container->hasDefinition('sofascore.purgatory.route_parameter_resolver.dynamic'));
    }

    public function testExceptionIsThrownWhenSameAliasIsUsedMultipleTimes(): void
    {
        $this->container->register(id: 'foo', class: \stdClass::class)->addTag(
            name: 'purgatory.route_parameter_service',
            attributes: ['alias' => 'one', 'method' => '__invoke'],
        );
        $this->container->register(id: 'bar', class: \stdClass::class)->addTag(
            name: 'purgatory.route_parameter_service',
            attributes: ['alias' => 'one', 'method' => '__invoke'],
        );

        $compilerPass = new RegisterRouteParamServicesPass();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The alias "one" is already used by "foo::__invoke".');

        $compilerPass->process($this->container);
    }
}
