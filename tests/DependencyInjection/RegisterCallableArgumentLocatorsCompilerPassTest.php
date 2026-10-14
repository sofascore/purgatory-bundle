<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Cache\RouteMetadata\YamlMetadataProvider;
use Sofascore\PurgatoryBundle\DependencyInjection\RegisterCallableArgumentLocatorsCompilerPass;
use Sofascore\PurgatoryBundle\PurgatoryBundle;
use Sofascore\PurgatoryBundle\RouteProvider\CallableInvoker;
use Sofascore\PurgatoryBundle\Tests\DependencyInjection\Fixtures\DummyControllerWithCallableServices;
use Sofascore\PurgatoryBundle\Tests\DependencyInjection\Fixtures\DummyRouteParamService;
use Sofascore\PurgatoryBundle\Tests\DependencyInjection\Fixtures\Php85DummyControllerWithClosureServices;
use Symfony\Component\Config\Resource\ReflectionClassResource;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\TypedReference;

#[CoversClass(RegisterCallableArgumentLocatorsCompilerPass::class)]
final class RegisterCallableArgumentLocatorsCompilerPassTest extends TestCase
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

    public function testStaticMethods(): void
    {
        $this->container->register('my.controller', DummyControllerWithCallableServices::class)
            ->addTag('purgatory.purge_on');

        (new RegisterCallableArgumentLocatorsCompilerPass())->process($this->container);

        $locators = $this->getLocators();

        self::assertSame([
            DummyControllerWithCallableServices::class.'::withServices',
            DummyControllerWithCallableServices::class.'::provide',
        ], array_keys($locators));

        self::assertEquals([
            'service' => new TypedReference(DummyRouteParamService::class, DummyRouteParamService::class, ContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE, 'service'),
            'nullable' => new TypedReference(\ArrayObject::class, \ArrayObject::class, ContainerInterface::NULL_ON_INVALID_REFERENCE, 'nullable'),
            'autowired' => new Reference('some_service'),
            'environment' => new Reference('.value.'.$this->container->hash('dev')),
            'optional' => new TypedReference(\SplQueue::class, \SplQueue::class, ContainerInterface::IGNORE_ON_INVALID_REFERENCE, 'optional'),
        ], $locators[DummyControllerWithCallableServices::class.'::withServices']);

        self::assertEquals([
            'countable' => new TypedReference(\Countable::class, \Countable::class, ContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE, 'someName', [new Target('someName')]),
        ], $locators[DummyControllerWithCallableServices::class.'::provide']);

        self::assertSame(['dev'], $this->container->getDefinition('.value.'.$this->container->hash('dev'))->getArguments()[0]);

        $resources = array_map('strval', $this->container->getResources());
        self::assertContains('reflection.'.DummyControllerWithCallableServices::class, $resources);
    }

    public function testStaticMethodsFromYaml(): void
    {
        $this->container->register('sofascore.purgatory.route_metadata_provider.yaml', YamlMetadataProvider::class)
            ->setArguments([null, [__DIR__.'/Fixtures/callables/purgatory.yaml']]);

        (new RegisterCallableArgumentLocatorsCompilerPass())->process($this->container);

        $locators = $this->getLocators();

        self::assertSame([
            DummyControllerWithCallableServices::class.'::withServices',
            DummyControllerWithCallableServices::class.'::provideFromYaml',
        ], array_keys($locators));

        self::assertEquals([
            'service' => new TypedReference(DummyRouteParamService::class, DummyRouteParamService::class, ContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE, 'service'),
        ], $locators[DummyControllerWithCallableServices::class.'::provideFromYaml']);

        $resources = array_filter($this->container->getResources(), static fn (object $resource): bool => $resource instanceof ReflectionClassResource);
        self::assertContains('reflection.'.DummyControllerWithCallableServices::class, array_map('strval', $resources));
    }

    #[RequiresPhp('>= 8.5.0')]
    public function testClosures(): void
    {
        $this->container->register('my.controller', Php85DummyControllerWithClosureServices::class)
            ->addTag('purgatory.purge_on');

        (new RegisterCallableArgumentLocatorsCompilerPass())->process($this->container);

        $locators = $this->getLocators();
        $purgeOn = (new \ReflectionMethod(Php85DummyControllerWithClosureServices::class, '__invoke'))->getAttributes()[0]->newInstance();

        /** @var \Closure $if */
        $if = $purgeOn->if;
        self::assertEquals([
            'service' => new TypedReference(DummyRouteParamService::class, DummyRouteParamService::class, ContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE, 'service'),
        ], $locators[CallableInvoker::key($if)]);

        /** @var \Closure $provider */
        $provider = $purgeOn->routeParams['foo']->provider;
        self::assertEquals([
            'countable' => new TypedReference(\Countable::class, \Countable::class, ContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE, 'countable'),
        ], $locators[CallableInvoker::key($provider)]);
    }

    public function testNothingToRegister(): void
    {
        (new RegisterCallableArgumentLocatorsCompilerPass())->process($this->container);

        self::assertSame([], $this->getLocators());
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getLocators(): array
    {
        /** @var Reference $reference */
        $reference = $this->container->getDefinition('sofascore.purgatory.callable_invoker')->getArgument(0);

        /** @var array<string, ServiceClosureArgument> $locators */
        $locators = $this->container->getDefinition((string) $reference)->getArgument(0);

        return array_map(function (ServiceClosureArgument $argument): array {
            /** @var Reference $reference */
            $reference = $argument->getValues()[0];
            $definition = $this->container->getDefinition((string) $reference);

            // a locator with a caller ID is derived from the shared one
            if (\is_array($factory = $definition->getFactory())) {
                self::assertSame('withContext', $factory[1]);
                $definition = $this->container->getDefinition((string) $factory[0]);
            }

            /** @var array<string, ServiceClosureArgument> $arguments */
            $arguments = $definition->getArgument(0);

            return array_map(static fn (ServiceClosureArgument $argument): mixed => $argument->getValues()[0], $arguments);
        }, $locators);
    }
}
