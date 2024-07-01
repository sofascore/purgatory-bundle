<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\DependencyInjection;

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

    public function testRouteIgnorePatternsIsSet(): void
    {
        $container = new ContainerBuilder();
        $extension = new PurgatoryExtension();
        $extension->load([
            'sofascore_purgatory' => [
                'route_ignore_patterns' => ['/^_profiler/'],
            ],
        ], $container);

        $ignoredPatterns = $container->getDefinition('sofascore.purgatory.controller_metadata_provider')->getArgument(2);

        self::assertCount(1, $ignoredPatterns);
        self::assertSame('/^_profiler/', $ignoredPatterns[0]);
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

        self::assertTrue($container->getDefinition(DummySubscriptionResolver::class)->hasTag('purgatory.subscription_resolver'));
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

        self::assertTrue($container->getDefinition(DummyTargetResolver::class)->hasTag('purgatory.target_resolver'));
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

        self::assertTrue($container->getDefinition(DummyRouteProvider::class)->hasTag('purgatory.route_provider'));
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

        self::assertTrue($container->getDefinition(DummyValuesResolver::class)->hasTag('purgatory.route_param_value_resolver'));
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

        self::assertTrue($container->hasParameter('.sofascore.purgatory.purger.name'));
        self::assertTrue($container->hasParameter('.sofascore.purgatory.purger.host'));

        self::assertSame('foo_purger', $container->getParameter('.sofascore.purgatory.purger.name'));
        self::assertSame('localhost:80', $container->getParameter('.sofascore.purgatory.purger.host'));
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

        self::assertTrue($container->hasAlias('sofascore.purgatory.purger'));
        self::assertSame('sofascore.purgatory.purger.null', (string) $container->getAlias('sofascore.purgatory.purger'));

        self::assertTrue($container->hasAlias(PurgerInterface::class));
        self::assertSame('sofascore.purgatory.purger', (string) $container->getAlias(PurgerInterface::class));
    }

    public function testMessengerWhenTransportIsNotSet(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension($extension = new PurgatoryExtension());

        $extension->prepend($container);

        self::assertSame([], $container->getExtensionConfig('framework'));

        $extension->load([], $container);

        self::assertFalse($container->hasDefinition('sofascore.purgatory.purger.async'));
        self::assertFalse($container->hasDefinition('sofascore.purgatory.purge_message_handler'));
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

        self::assertTrue($container->hasDefinition('sofascore.purgatory.purger.async'));
        self::assertTrue($container->hasDefinition('sofascore.purgatory.purge_message_handler'));

        $definition = $container->getDefinition('sofascore.purgatory.purger.async');
        self::assertEquals($expectedArguments, $definition->getArguments());

        $definition = $container->getDefinition('sofascore.purgatory.purge_message_handler');
        self::assertTrue($definition->hasTag('messenger.message_handler'));
        self::assertSame([$expectedTagAttributes], $definition->getTag('messenger.message_handler'));
    }
}
