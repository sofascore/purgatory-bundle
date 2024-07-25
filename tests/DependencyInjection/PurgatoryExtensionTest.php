<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\DependencyInjection;

use Doctrine\ORM\Events as DoctrineEvents;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\DataCollector\PurgatoryDataCollector;
use Sofascore\PurgatoryBundle\DependencyInjection\CompilerPass\RegisterPurgerPass;
use Sofascore\PurgatoryBundle\DependencyInjection\PurgatoryExtension;
use Sofascore\PurgatoryBundle\Exception\RuntimeException;
use Sofascore\PurgatoryBundle\Purger\Messenger\PurgeMessage;
use Sofascore\PurgatoryBundle\Purger\PurgerInterface;
use Sofascore\PurgatoryBundle\Tests\DependencyInjection\Fixtures\DummyController;
use Sofascore\PurgatoryBundle\Tests\DependencyInjection\Fixtures\DummyControllerWithPurgeOn;
use Sofascore\PurgatoryBundle\Tests\DependencyInjection\Fixtures\DummyExpressionLanguageFunction;
use Sofascore\PurgatoryBundle\Tests\DependencyInjection\Fixtures\DummyInvalidExpressionLanguageFunction;
use Sofascore\PurgatoryBundle\Tests\DependencyInjection\Fixtures\DummyInvalidRouteParamService;
use Sofascore\PurgatoryBundle\Tests\DependencyInjection\Fixtures\DummyRouteParamService;
use Sofascore\PurgatoryBundle\Tests\DependencyInjection\Fixtures\DummyRouteProvider;
use Sofascore\PurgatoryBundle\Tests\DependencyInjection\Fixtures\DummySubscriptionResolver;
use Sofascore\PurgatoryBundle\Tests\DependencyInjection\Fixtures\DummyTargetResolver;
use Sofascore\PurgatoryBundle\Tests\DependencyInjection\Fixtures\DummyValuesResolver;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Component\Config\Resource\ResourceInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PurgatoryExtension::class)]
final class PurgatoryExtensionTest extends TestCase
{
    public function testControllerWithPurgeOnIsTagged(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', __DIR__);
        $container->registerExtension($extension = new PurgatoryExtension());

        $container->register(DummyController::class)
            ->setAutoconfigured(true)
            ->setPublic(true);

        $container->register(DummyControllerWithPurgeOn::class)
            ->setAutoconfigured(true)
            ->setPublic(true);

        $container->loadFromExtension($extension->getAlias(), []);

        $container->compile();

        self::assertTrue($container->getDefinition(DummyController::class)->hasTag('purgatory.purge_on'));
        self::assertSame(
            [['class' => DummyController::class]],
            $container->getDefinition(DummyController::class)->getTag('purgatory.purge_on'),
        );

        self::assertTrue($container->getDefinition(DummyControllerWithPurgeOn::class)->hasTag('purgatory.purge_on'));
        self::assertSame(
            [['class' => DummyControllerWithPurgeOn::class]],
            $container->getDefinition(DummyControllerWithPurgeOn::class)->getTag('purgatory.purge_on'),
        );
    }

    public function testServiceWithAsRouteParamServiceIsTagged(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', __DIR__);
        $container->registerExtension($extension = new PurgatoryExtension());

        $container->register(DummyRouteParamService::class)
            ->setAutoconfigured(true)
            ->setPublic(true);

        $container->loadFromExtension($extension->getAlias(), []);

        $container->compile();

        self::assertTrue($container->getDefinition(DummyRouteParamService::class)->hasTag('purgatory.route_parameter_service'));
        self::assertSame(
            [['alias' => 'alias_class', 'method' => '__invoke'], ['alias' => 'alias_foo', 'method' => 'foo']],
            $container->getDefinition(DummyRouteParamService::class)->getTag('purgatory.route_parameter_service'),
        );
    }

    public function testExceptionIsThrownWhenRouteParamServiceMethodDoesNotExist(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', __DIR__);
        $container->registerExtension($extension = new PurgatoryExtension());

        $container->register(DummyInvalidRouteParamService::class)
            ->setAutoconfigured(true)
            ->setPublic(true);

        $container->loadFromExtension($extension->getAlias(), []);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(\sprintf('Invalid route parameter service, the method "%s::__invoke()" does not exist.', DummyInvalidRouteParamService::class));

        $container->compile();
    }

    public function testServiceWithAsExpressionLanguageFunctionIsTagged(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', __DIR__);
        $container->registerExtension($extension = new PurgatoryExtension());

        $container->register(DummyExpressionLanguageFunction::class)
            ->setAutoconfigured(true)
            ->setPublic(true);

        $container->loadFromExtension($extension->getAlias(), []);

        $container->compile();

        self::assertTrue($container->getDefinition(DummyExpressionLanguageFunction::class)->hasTag('purgatory.expression_language_function'));
        self::assertSame(
            [['function' => 'function_class', 'method' => '__invoke'], ['function' => 'function_foo', 'method' => 'foo']],
            $container->getDefinition(DummyExpressionLanguageFunction::class)->getTag('purgatory.expression_language_function'),
        );
    }

    public function testExceptionIsThrownWhenExpressionLanguageFunctionMethodDoesNotExist(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', __DIR__);
        $container->registerExtension($extension = new PurgatoryExtension());

        $container->register(DummyInvalidExpressionLanguageFunction::class)
            ->setAutoconfigured(true)
            ->setPublic(true);

        $container->loadFromExtension($extension->getAlias(), []);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(\sprintf('Invalid expression language function, the method "%s::__invoke()" does not exist.', DummyInvalidExpressionLanguageFunction::class));

        $container->compile();
    }

    #[TestWith([[], ['config/purgatory/one.yaml', 'config/purgatory/two.yml'], __DIR__.'/Fixtures/app/config/purgatory'])]
    #[TestWith([
        [__DIR__.'/Fixtures/app/config/three.yaml'],
        ['config/purgatory/one.yaml', 'config/purgatory/two.yml', 'config/three.yaml'],
        __DIR__.'/Fixtures/app/config/three.yaml',
    ])]
    #[TestWith([
        [__DIR__.'/Fixtures/app/config/additional'],
        ['config/purgatory/one.yaml', 'config/purgatory/two.yml', 'config/additional/five.yaml', 'config/additional/four.yml'],
        __DIR__.'/Fixtures/app/config/additional',
    ])]
    public function testMappingPathsAreSet(array $mappingPaths, array $expectedFiles, string $expectedResource): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', __DIR__.'/Fixtures/app');

        $extension = new PurgatoryExtension();
        $extension->load([
            'purgatory' => [
                'mapping_paths' => $mappingPaths,
            ],
        ], $container);

        self::assertTrue($container->hasDefinition('sofascore.purgatory.route_metadata_provider.yaml'));
        self::assertSame(
            array_map(static fn (string $file): string => __DIR__.'/Fixtures/app/'.$file, $expectedFiles),
            $container->getDefinition('sofascore.purgatory.route_metadata_provider.yaml')->getArgument(1),
        );

        self::assertContains($expectedResource, array_map(
            static fn (ResourceInterface $resource): string => method_exists($resource, 'getResource') ? $resource->getResource() : (string) $resource,
            $container->getResources(),
        ));
    }

    public function testYamlMetadataProviderIsRemovedWhenThereAreNoFiles(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', __DIR__);

        $extension = new PurgatoryExtension();
        $extension->load([], $container);

        self::assertFalse($container->hasDefinition('sofascore.purgatory.route_metadata_provider.yaml'));
    }

    public function testExceptionIsThrownOnInvalidMappingPath(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', __DIR__);

        $extension = new PurgatoryExtension();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Could not open file or directory "foobarbaz.yaml".');

        $extension->load([
            'purgatory' => [
                'mapping_paths' => ['foobarbaz.yaml'],
            ],
        ], $container);
    }

    public function testRouteIgnorePatternsIsSet(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', __DIR__);

        $extension = new PurgatoryExtension();
        $extension->load([
            'purgatory' => [
                'route_ignore_patterns' => ['/^_profiler/'],
            ],
        ], $container);

        $ignoredPatterns = $container->getDefinition('sofascore.purgatory.route_metadata_provider.attribute')->getArgument(2);

        self::assertCount(1, $ignoredPatterns);
        self::assertSame('/^_profiler/', $ignoredPatterns[0]);
    }

    #[TestWith([[], [[]]])]
    #[TestWith([['doctrine_middleware' => ['priority' => 10]], [['priority' => 10]]])]
    public function testDoctrineMiddlewareTagIsSet(array $middlewarePriority, array $expectedTag): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', __DIR__);

        $extension = new PurgatoryExtension();
        $extension->load([
            'purgatory' => $middlewarePriority,
        ], $container);

        self::assertTrue($container->hasDefinition('sofascore.purgatory.doctrine_middleware'));

        $definition = $container->getDefinition('sofascore.purgatory.doctrine_middleware');

        self::assertTrue($definition->hasTag('doctrine.middleware'));
        self::assertSame($expectedTag, $definition->getTag('doctrine.middleware'));
    }

    public function testDoctrineMiddlewareIsRemovedWhenDisabled(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', __DIR__);

        $extension = new PurgatoryExtension();
        $extension->load([
            'purgatory' => ['doctrine_middleware' => false],
        ], $container);

        self::assertFalse($container->hasDefinition('sofascore.purgatory.doctrine_middleware'));
    }

    #[TestWith([[
        'doctrine_middleware' => true,
    ], [
        ['event' => DoctrineEvents::preRemove],
        ['event' => DoctrineEvents::postPersist],
        ['event' => DoctrineEvents::postUpdate],
    ]])]
    #[TestWith([[
        'doctrine_middleware' => true,
        'doctrine_event_listener_priorities' => [DoctrineEvents::preRemove => 10],
    ], [
        ['event' => DoctrineEvents::preRemove, 'priority' => 10],
        ['event' => DoctrineEvents::postPersist],
        ['event' => DoctrineEvents::postUpdate],
    ]])]
    #[TestWith([[
        'doctrine_middleware' => true,
        'doctrine_event_listener_priorities' => [DoctrineEvents::postPersist => 10, DoctrineEvents::postUpdate => 20],
    ], [
        ['event' => DoctrineEvents::postPersist, 'priority' => 10],
        ['event' => DoctrineEvents::postUpdate, 'priority' => 20],
        ['event' => DoctrineEvents::preRemove],
    ]])]
    #[TestWith([[
        'doctrine_middleware' => false,
    ], [
        ['event' => DoctrineEvents::preRemove],
        ['event' => DoctrineEvents::postPersist],
        ['event' => DoctrineEvents::postUpdate],
        ['event' => DoctrineEvents::postFlush],
    ]])]
    #[TestWith([[
        'doctrine_middleware' => false,
        'doctrine_event_listener_priorities' => [DoctrineEvents::preRemove => 10],
    ], [
        ['event' => DoctrineEvents::preRemove, 'priority' => 10],
        ['event' => DoctrineEvents::postPersist],
        ['event' => DoctrineEvents::postUpdate],
        ['event' => DoctrineEvents::postFlush],
    ]])]
    #[TestWith([[
        'doctrine_middleware' => false,
        'doctrine_event_listener_priorities' => [DoctrineEvents::postPersist => 10, DoctrineEvents::postUpdate => 20],
    ], [
        ['event' => DoctrineEvents::postPersist, 'priority' => 10],
        ['event' => DoctrineEvents::postUpdate, 'priority' => 20],
        ['event' => DoctrineEvents::preRemove],
        ['event' => DoctrineEvents::postFlush],
    ]])]
    public function testDoctrineEventListenerTagIsSet(array $config, array $expectedTag): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', __DIR__);

        $extension = new PurgatoryExtension();
        $extension->load([
            'purgatory' => $config,
        ], $container);

        $definition = $container->getDefinition('sofascore.purgatory.entity_change_listener');

        self::assertTrue($definition->hasTag('doctrine.event_listener'));
        self::assertSame($expectedTag, $definition->getTag('doctrine.event_listener'));
    }

    public function testSubscriptionResolverIsTagged(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', __DIR__);
        $container->registerExtension($extension = new PurgatoryExtension());

        $container->register(DummySubscriptionResolver::class)
            ->setAutoconfigured(true)
            ->setPublic(true);

        $container->loadFromExtension($extension->getAlias(), []);

        $container->compile();

        self::assertTrue($container->getDefinition(DummySubscriptionResolver::class)->hasTag('purgatory.subscription_resolver'));
    }

    public function testTargetResolverIsTagged(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', __DIR__);
        $container->registerExtension($extension = new PurgatoryExtension());

        $container->register(DummyTargetResolver::class)
            ->setAutoconfigured(true)
            ->setPublic(true);

        $container->loadFromExtension($extension->getAlias(), []);

        $container->compile();

        self::assertTrue($container->getDefinition(DummyTargetResolver::class)->hasTag('purgatory.target_resolver'));
    }

    public function testRouteProviderIsTagged(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', __DIR__);
        $container->registerExtension($extension = new PurgatoryExtension());

        $container->register(DummyRouteProvider::class)
            ->setAutoconfigured(true)
            ->setPublic(true);

        $container->loadFromExtension($extension->getAlias(), []);

        $container->compile();

        self::assertTrue($container->getDefinition(DummyRouteProvider::class)->hasTag('purgatory.route_provider'));
    }

    public function testRouteParamValuesResolverIsTagged(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', __DIR__);
        $container->registerExtension($extension = new PurgatoryExtension());

        $container->register(DummyValuesResolver::class)
            ->setAutoconfigured(true)
            ->setPublic(true);

        $container->loadFromExtension($extension->getAlias(), []);

        $container->compile();

        self::assertTrue($container->getDefinition(DummyValuesResolver::class)->hasTag('purgatory.route_param_value_resolver'));
    }

    public function testPurgerConfig(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', __DIR__);

        $extension = new PurgatoryExtension();
        $extension->load([
            'purgatory' => [
                'purger' => [
                    'name' => 'foo_purger',
                    'hosts' => ['http://localhost:80'],
                ],
            ],
        ], $container);

        self::assertTrue($container->hasParameter('.sofascore.purgatory.purger.name'));
        self::assertTrue($container->hasParameter('.sofascore.purgatory.purger.hosts'));

        self::assertSame('foo_purger', $container->getParameter('.sofascore.purgatory.purger.name'));
        self::assertSame(['http://localhost:80'], $container->getParameter('.sofascore.purgatory.purger.hosts'));
    }

    public function testDefaultPurgerIsSetToVoidPurger(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', __DIR__);

        $extension = new PurgatoryExtension();
        $extension->load([
            'purgatory' => [
                'purger' => [
                    'name' => 'foo_purger',
                    'hosts' => ['http://localhost:80'],
                ],
            ],
        ], $container);

        self::assertTrue($container->hasAlias('sofascore.purgatory.purger'));
        self::assertSame('sofascore.purgatory.purger.void', (string) $container->getAlias('sofascore.purgatory.purger'));

        self::assertTrue($container->hasAlias(PurgerInterface::class));
        self::assertSame('sofascore.purgatory.purger', (string) $container->getAlias(PurgerInterface::class));
    }

    #[TestWith([[], 'http_client'])]
    #[TestWith([['http_client' => 'foo.client'], 'foo.client'])]
    public function testCorrectHttpClientIsSet(array $config, string $expectedHttpClient): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', __DIR__);

        $extension = new PurgatoryExtension();
        $extension->load([
            'purgatory' => [
                'purger' => $config,
            ],
        ], $container);

        self::assertTrue($container->has('sofascore.purgatory.purger.varnish'));
        self::assertSame($expectedHttpClient, (string) $container->getDefinition('sofascore.purgatory.purger.varnish')->getArgument(0));
    }

    public function testMessengerWhenTransportIsNotSet(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', __DIR__);
        $container->registerExtension($extension = new PurgatoryExtension());

        $extension->prepend($container);

        self::assertSame([], $container->getExtensionConfig('framework'));

        $extension->load([], $container);

        self::assertTrue($container->hasParameter('.sofascore.purgatory.purger.async_transport'));
        self::assertNull($container->getParameter('.sofascore.purgatory.purger.async_transport'));

        self::assertFalse($container->hasDefinition('sofascore.purgatory.purger.async'));
        self::assertFalse($container->hasDefinition('sofascore.purgatory.purge_message_handler'));
    }

    #[TestWith([[], [new Reference('messenger.default_bus')], []])]
    #[TestWith([['bus' => 'foo.bar'], [new Reference('foo.bar')], ['bus' => 'foo.bar']])]
    #[TestWith([['batch_size' => 3], [new Reference('messenger.default_bus'), 3], []])]
    public function testMessengerWhenTransportIsSet(array $extraConfig, array $expectedArguments, array $expectedTagAttributes): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', __DIR__);
        $container->registerExtension($extension = new PurgatoryExtension());

        $container->loadFromExtension($extension->getAlias(), [
            'messenger' => [
                'transport' => 'foo',
                ...$extraConfig,
            ],
        ]);

        $extension->prepend($container);

        self::assertSame([
            [
                'messenger' => [
                    'routing' => [
                        PurgeMessage::class => 'foo',
                    ],
                ],
            ],
        ], $container->getExtensionConfig('framework'));

        $extension->load($container->getExtensionConfig('purgatory'), $container);

        self::assertTrue($container->hasParameter('.sofascore.purgatory.purger.async_transport'));
        self::assertSame('foo', $container->getParameter('.sofascore.purgatory.purger.async_transport'));

        self::assertTrue($container->hasDefinition('sofascore.purgatory.purger.async'));
        self::assertTrue($container->hasDefinition('sofascore.purgatory.purge_message_handler'));

        $definition = $container->getDefinition('sofascore.purgatory.purger.async');
        self::assertEquals($expectedArguments, $definition->getArguments());

        $definition = $container->getDefinition('sofascore.purgatory.purge_message_handler');
        self::assertTrue($definition->hasTag('messenger.message_handler'));
        self::assertSame([$expectedTagAttributes], $definition->getTag('messenger.message_handler'));
    }

    /**
     * @param list<ExtensionInterface> $extensions
     */
    #[TestWith([[new PurgatoryExtension()], false])]
    #[TestWith([[new FrameworkExtension(), new PurgatoryExtension()], true])]
    #[TestWith([[new PurgatoryExtension(), new FrameworkExtension()], true])]
    public function testExpressionLanguageCacheIsRemovedWhenExpected(array $extensions, bool $hasCache): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', __DIR__);
        $container->setParameter('kernel.debug', true);
        $container->setParameter('kernel.build_dir', __DIR__);
        $container->setParameter('kernel.container_class', 'App');

        $container->getCompilerPassConfig()->setRemovingPasses([]);

        foreach ($extensions as $extension) {
            $container->registerExtension($extension);
            $container->loadFromExtension($extension->getAlias(), []);
        }

        $container->compile();

        self::assertSame($hasCache, $container->hasDefinition('sofascore.purgatory.cache.expression_language'));
    }

    #[TestWith([['profiler_integration' => true], true, true, false])]
    #[TestWith([['profiler_integration' => true, 'messenger' => 'async'], true, true, true])]
    #[TestWith([['profiler_integration' => false], false, false, false])]
    public function testProfilerIntegration(
        array $config,
        bool $hasDataCollector,
        bool $hasTraceablePurger,
        bool $hasTraceableSyncPurger,
    ): void {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', __DIR__);
        $container->registerExtension($extension = new PurgatoryExtension());

        $container->getCompilerPassConfig()->setRemovingPasses([]);

        $container->register('profiler', \stdClass::class);
        $container->register('twig', \stdClass::class);

        $container->loadFromExtension($extension->getAlias(), $config);

        $container->compile();

        self::assertSame($hasDataCollector, $container->hasDefinition('sofascore.purgatory.data_collector'));
        self::assertSame($hasTraceablePurger, $container->hasDefinition('sofascore.purgatory.purger.traceable'));
        self::assertSame($hasTraceableSyncPurger, $container->hasDefinition('sofascore.purgatory.purger.sync.traceable'));
    }

    #[TestWith([['profiler_integration' => true]])]
    #[TestWith([['profiler_integration' => true, 'messenger' => 'async']])]
    #[TestWith([['profiler_integration' => false]])]
    public function testProfilerIntegrationWithoutProfiler(array $config): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', __DIR__);
        $container->registerExtension($extension = new PurgatoryExtension());

        $container->getCompilerPassConfig()->setRemovingPasses([]);

        $container->loadFromExtension($extension->getAlias(), $config);

        $container->compile();

        self::assertFalse($container->hasDefinition('sofascore.purgatory.data_collector'));
        self::assertFalse($container->hasDefinition('sofascore.purgatory.purger.traceable'));
        self::assertFalse($container->hasDefinition('sofascore.purgatory.purger.sync.traceable'));
    }

    public function testDataCollectorServiceCanBeInstantiatedWhenPurgerNameIsNull(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', __DIR__);
        $container->registerExtension($extension = new PurgatoryExtension());
        $container->addCompilerPass(new RegisterPurgerPass());

        $container->register('profiler', \stdClass::class);
        $container->register('twig', \stdClass::class);

        $container->setAlias('sofascore.purgatory.data_collector.public', 'sofascore.purgatory.data_collector')
            ->setPublic(true);

        $container->loadFromExtension($extension->getAlias(), []);

        $container->compile();

        self::assertInstanceOf(PurgatoryDataCollector::class, $container->get('sofascore.purgatory.data_collector.public'));
    }
}
