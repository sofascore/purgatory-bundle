<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\DependencyInjection\RegisterRouteParamServicesCompilerPass;
use Sofascore\PurgatoryBundle\Exception\RuntimeException;
use Sofascore\PurgatoryBundle\PurgatoryBundle;
use Sofascore\PurgatoryBundle\Tests\DependencyInjection\Fixtures\DummyInvalidRouteParamService;
use Sofascore\PurgatoryBundle\Tests\DependencyInjection\Fixtures\DummyMagicCallService;
use Sofascore\PurgatoryBundle\Tests\DependencyInjection\Fixtures\DummyNonPublicMethodService;
use Sofascore\PurgatoryBundle\Tests\DependencyInjection\Fixtures\DummyRouteParamService;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;

#[CoversClass(RegisterRouteParamServicesCompilerPass::class)]
final class RegisterRouteParamServicesCompilerPassTest extends TestCase
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
        $this->container->register(id: 'foo', class: DummyRouteParamService::class)
            ->addTag(
                name: 'purgatory.route_parameter_service',
                attributes: ['alias' => 'one', 'method' => '__invoke'],
            )
            ->addTag(
                name: 'purgatory.route_parameter_service',
                attributes: ['alias' => 'two', 'method' => 'foo'],
            );
        $this->container->register(id: 'bar', class: DummyRouteParamService::class)
            ->addTag(
                name: 'purgatory.route_parameter_service',
                attributes: ['alias' => 'three', 'method' => 'foo'],
            );
        $this->container->register(id: 'baz', class: DummyRouteParamService::class)
            ->addTag(
                name: 'purgatory.route_parameter_service',
                attributes: ['alias' => 'four'],
            );

        $compilerPass = new RegisterRouteParamServicesCompilerPass();
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
        $serviceDefinition = $argument['one']->getValues()[0];
        self::assertSame(\Closure::class, $serviceDefinition->getClass());
        self::assertEquals([[new Reference('foo'), '__invoke']], $serviceDefinition->getArguments());
        self::assertSame([\Closure::class, 'fromCallable'], $serviceDefinition->getFactory());

        self::assertArrayHasKey('two', $argument);
        self::assertInstanceOf(ServiceClosureArgument::class, $argument['two']);
        /** @var Definition $serviceDefinition */
        $serviceDefinition = $argument['two']->getValues()[0];
        self::assertSame(\Closure::class, $serviceDefinition->getClass());
        self::assertEquals([[new Reference('foo'), 'foo']], $serviceDefinition->getArguments());
        self::assertSame([\Closure::class, 'fromCallable'], $serviceDefinition->getFactory());

        self::assertArrayHasKey('three', $argument);
        self::assertInstanceOf(ServiceClosureArgument::class, $argument['three']);
        /** @var Definition $serviceDefinition */
        $serviceDefinition = $argument['three']->getValues()[0];
        self::assertSame(\Closure::class, $serviceDefinition->getClass());
        self::assertEquals([[new Reference('bar'), 'foo']], $serviceDefinition->getArguments());
        self::assertSame([\Closure::class, 'fromCallable'], $serviceDefinition->getFactory());

        self::assertArrayHasKey('four', $argument);
        self::assertInstanceOf(ServiceClosureArgument::class, $argument['four']);
        /** @var Definition $serviceDefinition */
        $serviceDefinition = $argument['four']->getValues()[0];
        self::assertSame(\Closure::class, $serviceDefinition->getClass());
        self::assertEquals([[new Reference('baz'), '__invoke']], $serviceDefinition->getArguments());
        self::assertSame([\Closure::class, 'fromCallable'], $serviceDefinition->getFactory());
    }

    public function testDynamicResolverIsKeptWhenThereAreNoServices(): void
    {
        $compilerPass = new RegisterRouteParamServicesCompilerPass();
        $compilerPass->process($this->container);

        self::assertTrue($this->container->hasDefinition('sofascore.purgatory.route_parameter_resolver.dynamic'));
        self::assertInstanceOf(
            Reference::class,
            $this->container->getDefinition('sofascore.purgatory.route_parameter_resolver.dynamic')->getArgument(0),
        );
    }

    public function testExceptionIsThrownWhenSameAliasIsUsedMultipleTimes(): void
    {
        $this->container->register(id: 'foo', class: DummyRouteParamService::class)->addTag(
            name: 'purgatory.route_parameter_service',
            attributes: ['alias' => 'one', 'method' => '__invoke'],
        );
        $this->container->register(id: 'bar', class: DummyRouteParamService::class)->addTag(
            name: 'purgatory.route_parameter_service',
            attributes: ['alias' => 'one', 'method' => '__invoke'],
        );

        $compilerPass = new RegisterRouteParamServicesCompilerPass();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The alias "one" is already used by "foo::__invoke".');

        $compilerPass->process($this->container);
    }

    #[TestWith([DummyRouteParamService::class, 'bar'])]
    #[TestWith([DummyInvalidRouteParamService::class, null])]
    #[TestWith([DummyNonPublicMethodService::class, 'foo'])]
    public function testExceptionIsThrownWhenMethodDoesNotExistOrIsNotPublic(string $class, ?string $method): void
    {
        $this->container->register(id: 'foo', class: $class)->addTag(
            name: 'purgatory.route_parameter_service',
            attributes: ['alias' => 'one'] + (null !== $method ? ['method' => $method] : []),
        );

        $compilerPass = new RegisterRouteParamServicesCompilerPass();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(\sprintf('Invalid route parameter service, the method "%s::%s()" does not exist or is not public.', $class, $method ?? '__invoke'));

        $compilerPass->process($this->container);
    }

    public function testMethodHandledByMagicCallIsAllowed(): void
    {
        $this->container->register(id: 'foo', class: DummyMagicCallService::class)->addTag(
            name: 'purgatory.route_parameter_service',
            attributes: ['alias' => 'one', 'method' => 'bar'],
        );

        $compilerPass = new RegisterRouteParamServicesCompilerPass();
        $compilerPass->process($this->container);

        self::assertTrue($this->container->hasDefinition('sofascore.purgatory.route_parameter_resolver.dynamic'));
    }
}
