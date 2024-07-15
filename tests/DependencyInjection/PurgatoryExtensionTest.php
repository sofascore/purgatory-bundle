<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\DependencyInjection;

use Doctrine\ORM\Events as DoctrineEvents;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle2\DependencyInjection\PurgatoryExtension;
use Sofascore\PurgatoryBundle2\Exception\RuntimeException;
use Sofascore\PurgatoryBundle2\Purger\Messenger\PurgeMessage;
use Sofascore\PurgatoryBundle2\Purger\PurgerInterface;
use Sofascore\PurgatoryBundle2\Tests\DependencyInjection\Fixtures\DummyController;
use Sofascore\PurgatoryBundle2\Tests\DependencyInjection\Fixtures\DummyControllerWithPurgeOn;
use Sofascore\PurgatoryBundle2\Tests\DependencyInjection\Fixtures\DummyInvalidRouteParamService;
use Sofascore\PurgatoryBundle2\Tests\DependencyInjection\Fixtures\DummyRouteParamService;
use Sofascore\PurgatoryBundle2\Tests\DependencyInjection\Fixtures\DummyRouteProvider;
use Sofascore\PurgatoryBundle2\Tests\DependencyInjection\Fixtures\DummySubscriptionResolver;
use Sofascore\PurgatoryBundle2\Tests\DependencyInjection\Fixtures\DummyTargetResolver;
use Sofascore\PurgatoryBundle2\Tests\DependencyInjection\Fixtures\DummyValuesResolver;
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

        self::assertTrue($container->getDefinition(DummyController::class)->hasTag('purgatory2.purge_on'));
        self::assertSame(
            [['class' => DummyController::class]],
            $container->getDefinition(DummyController::class)->getTag('purgatory2.purge_on'),
        );

        self::assertTrue($container->getDefinition(DummyControllerWithPurgeOn::class)->hasTag('purgatory2.purge_on'));
        self::assertSame(
            [['class' => DummyControllerWithPurgeOn::class]],
            $container->getDefinition(DummyControllerWithPurgeOn::class)->getTag('purgatory2.purge_on'),
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

        self::assertTrue($container->getDefinition(DummyRouteParamService::class)->hasTag('purgatory2.route_parameter_service'));
        self::assertSame(
            [['alias' => 'alias_class', 'method' => '__invoke'], ['alias' => 'alias_foo', 'method' => 'foo']],
            $container->getDefinition(DummyRouteParamService::class)->getTag('purgatory2.route_parameter_service'),
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
        $this->expectExceptionMessage(sprintf('Invalid route parameter service, the method "%s::__invoke()" does not exist.', DummyInvalidRouteParamService::class));

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
            'sofascore_purgatory' => [
                'mapping_paths' => $mappingPaths,
            ],
        ], $container);

        self::assertTrue($container->hasDefinition('sofascore.purgatory2.route_metadata_provider.yaml'));
        self::assertSame(
            array_map(static fn (string $file): string => __DIR__.'/Fixtures/app/'.$file, $expectedFiles),
            $container->getDefinition('sofascore.purgatory2.route_metadata_provider.yaml')->getArgument(1),
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

        self::assertFalse($container->hasDefinition('sofascore.purgatory2.route_metadata_provider.yaml'));
    }

    public function testExceptionIsThrownOnInvalidMappingPath(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', __DIR__);

        $extension = new PurgatoryExtension();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Could not open file or directory "foobarbaz.yaml".');

        $extension->load([
            'sofascore_purgatory' => [
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
            'sofascore_purgatory' => [
                'route_ignore_patterns' => ['/^_profiler/'],
            ],
        ], $container);

        $ignoredPatterns = $container->getDefinition('sofascore.purgatory2.route_metadata_provider.attribute')->getArgument(2);

        self::assertCount(1, $ignoredPatterns);
        self::assertSame('/^_profiler/', $ignoredPatterns[0]);
    }

    #[TestWith([[], [[]]])]
    #[TestWith([['doctrine_middleware_priority' => 10], [['priority' => 10]]])]
    public function testDoctrineMiddlewareTagIsSet(array $middlewarePriority, array $expectedTag): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', __DIR__);

        $extension = new PurgatoryExtension();
        $extension->load([
            'sofascore_purgatory' => $middlewarePriority,
        ], $container);

        $definition = $container->getDefinition('sofascore.purgatory2.doctrine_middleware');

        self::assertTrue($definition->hasTag('doctrine.middleware'));
        self::assertSame($expectedTag, $definition->getTag('doctrine.middleware'));
    }

    #[TestWith([
        [],
        [['event' => DoctrineEvents::preRemove], ['event' => DoctrineEvents::postPersist], ['event' => DoctrineEvents::postUpdate]],
    ])]
    #[TestWith([
        ['doctrine_event_listener_priorities' => [DoctrineEvents::preRemove => 10]],
        [['event' => DoctrineEvents::preRemove, 'priority' => 10], ['event' => DoctrineEvents::postPersist], ['event' => DoctrineEvents::postUpdate]],
    ])]
    #[TestWith([
        ['doctrine_event_listener_priorities' => [DoctrineEvents::postPersist => 10, DoctrineEvents::postUpdate => 20]],
        [['event' => DoctrineEvents::postPersist, 'priority' => 10], ['event' => DoctrineEvents::postUpdate, 'priority' => 20], ['event' => DoctrineEvents::preRemove]],
    ])]
    public function testDoctrineEventListenerTagIsSet(array $middlewarePriority, array $expectedTag): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', __DIR__);

        $extension = new PurgatoryExtension();
        $extension->load([
            'sofascore_purgatory' => $middlewarePriority,
        ], $container);

        $definition = $container->getDefinition('sofascore.purgatory2.entity_change_listener');

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

        self::assertTrue($container->getDefinition(DummySubscriptionResolver::class)->hasTag('purgatory2.subscription_resolver'));
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

        self::assertTrue($container->getDefinition(DummyTargetResolver::class)->hasTag('purgatory2.target_resolver'));
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

        self::assertTrue($container->getDefinition(DummyRouteProvider::class)->hasTag('purgatory2.route_provider'));
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

        self::assertTrue($container->getDefinition(DummyValuesResolver::class)->hasTag('purgatory2.route_param_value_resolver'));
    }

    public function testPurgerConfig(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', __DIR__);

        $extension = new PurgatoryExtension();
        $extension->load([
            'sofascore_purgatory' => [
                'purger' => [
                    'name' => 'foo_purger',
                    'host' => 'localhost:80',
                ],
            ],
        ], $container);

        self::assertTrue($container->hasParameter('.sofascore.purgatory2.purger.name'));
        self::assertTrue($container->hasParameter('.sofascore.purgatory2.purger.host'));

        self::assertSame('foo_purger', $container->getParameter('.sofascore.purgatory2.purger.name'));
        self::assertSame('localhost:80', $container->getParameter('.sofascore.purgatory2.purger.host'));
    }

    public function testDefaultPurgerIsSetToVoidPurger(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', __DIR__);

        $extension = new PurgatoryExtension();
        $extension->load([
            'sofascore_purgatory' => [
                'purger' => [
                    'name' => 'foo_purger',
                    'host' => 'localhost:80',
                ],
            ],
        ], $container);

        self::assertTrue($container->hasAlias('sofascore.purgatory2.purger'));
        self::assertSame('sofascore.purgatory2.purger.void', (string) $container->getAlias('sofascore.purgatory2.purger'));

        self::assertTrue($container->hasAlias(PurgerInterface::class));
        self::assertSame('sofascore.purgatory2.purger', (string) $container->getAlias(PurgerInterface::class));
    }

    public function testMessengerWhenTransportIsNotSet(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', __DIR__);
        $container->registerExtension($extension = new PurgatoryExtension());

        $extension->prepend($container);

        self::assertSame([], $container->getExtensionConfig('framework'));

        $extension->load([], $container);

        self::assertTrue($container->hasParameter('.sofascore.purgatory2.purger.async_transport'));
        self::assertNull($container->getParameter('.sofascore.purgatory2.purger.async_transport'));

        self::assertFalse($container->hasDefinition('sofascore.purgatory2.purger.async'));
        self::assertFalse($container->hasDefinition('sofascore.purgatory2.purge_message_handler'));
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

        $extension->load($container->getExtensionConfig('sofascore_purgatory'), $container);

        self::assertTrue($container->hasParameter('.sofascore.purgatory2.purger.async_transport'));
        self::assertSame('foo', $container->getParameter('.sofascore.purgatory2.purger.async_transport'));

        self::assertTrue($container->hasDefinition('sofascore.purgatory2.purger.async'));
        self::assertTrue($container->hasDefinition('sofascore.purgatory2.purge_message_handler'));

        $definition = $container->getDefinition('sofascore.purgatory2.purger.async');
        self::assertEquals($expectedArguments, $definition->getArguments());

        $definition = $container->getDefinition('sofascore.purgatory2.purge_message_handler');
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

        self::assertSame($hasCache, $container->hasDefinition('sofascore.purgatory2.cache.expression_language'));
    }
}
