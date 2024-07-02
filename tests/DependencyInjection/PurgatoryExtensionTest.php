<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\DependencyInjection;

use Doctrine\ORM\Events as DoctrineEvents;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle2\DependencyInjection\PurgatoryExtension;
use Sofascore\PurgatoryBundle2\Purger\Messenger\PurgeMessage;
use Sofascore\PurgatoryBundle2\Purger\PurgerInterface;
use Sofascore\PurgatoryBundle2\Tests\DependencyInjection\Fixtures\DummyController;
use Sofascore\PurgatoryBundle2\Tests\DependencyInjection\Fixtures\DummyControllerWithPurgeOn;
use Sofascore\PurgatoryBundle2\Tests\DependencyInjection\Fixtures\DummyRouteProvider;
use Sofascore\PurgatoryBundle2\Tests\DependencyInjection\Fixtures\DummySubscriptionResolver;
use Sofascore\PurgatoryBundle2\Tests\DependencyInjection\Fixtures\DummyTargetResolver;
use Sofascore\PurgatoryBundle2\Tests\DependencyInjection\Fixtures\DummyValuesResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PurgatoryExtension::class)]
final class PurgatoryExtensionTest extends TestCase
{
    public function testControllerWithPurgeOnIsTagged(): void
    {
        $container = new ContainerBuilder();

        $extension = new PurgatoryExtension();
        $extension->load([], $container);

        $container->register(DummyController::class)
            ->setAutoconfigured(true)
            ->setPublic(true);

        $container->register(DummyControllerWithPurgeOn::class)
            ->setAutoconfigured(true)
            ->setPublic(true);

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

    public function testRouteIgnorePatternsIsSet(): void
    {
        $container = new ContainerBuilder();
        $extension = new PurgatoryExtension();
        $extension->load([
            'sofascore_purgatory' => [
                'route_ignore_patterns' => ['/^_profiler/'],
            ],
        ], $container);

        $ignoredPatterns = $container->getDefinition('sofascore.purgatory2.controller_metadata_provider')->getArgument(2);

        self::assertCount(1, $ignoredPatterns);
        self::assertSame('/^_profiler/', $ignoredPatterns[0]);
    }

    #[TestWith([[], [[]]])]
    #[TestWith([['doctrine_middleware_priority' => 10], [['priority' => 10]]])]
    public function testDoctrineMiddlewareTagIsSet(array $middlewarePriority, array $expectedTag): void
    {
        $container = new ContainerBuilder();
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

        $container->register(DummySubscriptionResolver::class)
            ->setAutoconfigured(true)
            ->setPublic(true);

        $extension = new PurgatoryExtension();
        $extension->load([], $container);

        $container->compile();

        self::assertTrue($container->getDefinition(DummySubscriptionResolver::class)->hasTag('purgatory2.subscription_resolver'));
    }

    public function testTargetResolverIsTagged(): void
    {
        $container = new ContainerBuilder();

        $container->register(DummyTargetResolver::class)
            ->setAutoconfigured(true)
            ->setPublic(true);

        $extension = new PurgatoryExtension();
        $extension->load([], $container);

        $container->compile();

        self::assertTrue($container->getDefinition(DummyTargetResolver::class)->hasTag('purgatory2.target_resolver'));
    }

    public function testRouteProviderIsTagged(): void
    {
        $container = new ContainerBuilder();

        $container->register(DummyRouteProvider::class)
            ->setAutoconfigured(true)
            ->setPublic(true);

        $extension = new PurgatoryExtension();
        $extension->load([], $container);

        $container->compile();

        self::assertTrue($container->getDefinition(DummyRouteProvider::class)->hasTag('purgatory2.route_provider'));
    }

    public function testRouteParamValuesResolverIsTagged(): void
    {
        $container = new ContainerBuilder();

        $container->register(DummyValuesResolver::class)
            ->setAutoconfigured(true)
            ->setPublic(true);

        $extension = new PurgatoryExtension();
        $extension->load([], $container);

        $container->compile();

        self::assertTrue($container->getDefinition(DummyValuesResolver::class)->hasTag('purgatory2.route_param_value_resolver'));
    }

    public function testPurgerConfig(): void
    {
        $container = new ContainerBuilder();

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

    public function testDefaultPurgerIsSetToNullPurger(): void
    {
        $container = new ContainerBuilder();

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
        self::assertSame('sofascore.purgatory2.purger.null', (string) $container->getAlias('sofascore.purgatory2.purger'));

        self::assertTrue($container->hasAlias(PurgerInterface::class));
        self::assertSame('sofascore.purgatory2.purger', (string) $container->getAlias(PurgerInterface::class));
    }

    public function testMessengerWhenTransportIsNotSet(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension($extension = new PurgatoryExtension());

        $extension->prepend($container);

        self::assertSame([], $container->getExtensionConfig('framework'));

        $extension->load([], $container);

        self::assertFalse($container->hasDefinition('sofascore.purgatory2.purger.async'));
        self::assertFalse($container->hasDefinition('sofascore.purgatory2.purge_message_handler'));
    }

    #[TestWith([[], [new Reference('messenger.default_bus')], []])]
    #[TestWith([['bus' => 'foo.bar'], [new Reference('foo.bar')], ['bus' => 'foo.bar']])]
    #[TestWith([['batch_size' => 3], [new Reference('messenger.default_bus'), 3], []])]
    public function testMessengerWhenTransportIsSet(array $extraConfig, array $expectedArguments, array $expectedTagAttributes): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension($extension = new PurgatoryExtension());

        $container->loadFromExtension('sofascore_purgatory', [
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

        self::assertTrue($container->hasDefinition('sofascore.purgatory2.purger.async'));
        self::assertTrue($container->hasDefinition('sofascore.purgatory2.purge_message_handler'));

        $definition = $container->getDefinition('sofascore.purgatory2.purger.async');
        self::assertEquals($expectedArguments, $definition->getArguments());

        $definition = $container->getDefinition('sofascore.purgatory2.purge_message_handler');
        self::assertTrue($definition->hasTag('messenger.message_handler'));
        self::assertSame([$expectedTagAttributes], $definition->getTag('messenger.message_handler'));
    }
}
