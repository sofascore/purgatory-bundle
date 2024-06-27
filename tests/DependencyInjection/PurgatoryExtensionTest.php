<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle2\Cache\Metadata\ControllerMetadataProvider;
use Sofascore\PurgatoryBundle2\Cache\PropertyResolver\AssociationResolver;
use Sofascore\PurgatoryBundle2\Cache\PropertyResolver\EmbeddableResolver;
use Sofascore\PurgatoryBundle2\Cache\PropertyResolver\MethodResolver;
use Sofascore\PurgatoryBundle2\Cache\PropertyResolver\PropertyResolver;
use Sofascore\PurgatoryBundle2\Cache\TargetResolver\ForGroupsResolver;
use Sofascore\PurgatoryBundle2\Cache\TargetResolver\ForPropertiesResolver;
use Sofascore\PurgatoryBundle2\DependencyInjection\PurgatoryExtension;
use Sofascore\PurgatoryBundle2\Purger\Messenger\PurgeMessage;
use Sofascore\PurgatoryBundle2\Purger\PurgerInterface;
use Sofascore\PurgatoryBundle2\RouteParamValueResolver\CompoundValuesResolver;
use Sofascore\PurgatoryBundle2\RouteParamValueResolver\EnumValuesResolver;
use Sofascore\PurgatoryBundle2\RouteParamValueResolver\PropertyValuesResolver;
use Sofascore\PurgatoryBundle2\RouteParamValueResolver\RawValuesResolver;
use Sofascore\PurgatoryBundle2\RouteProvider\CreatedEntityRouteProvider;
use Sofascore\PurgatoryBundle2\RouteProvider\RemovedEntityRouteProvider;
use Sofascore\PurgatoryBundle2\RouteProvider\UpdatedEntityRouteProvider;
use Sofascore\PurgatoryBundle2\Tests\DependencyInjection\Fixtures\DummyController;
use Sofascore\PurgatoryBundle2\Tests\DependencyInjection\Fixtures\DummyControllerWithPurgeOn;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Routing\RouterInterface;

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

        $container->register('sofascore.purgatory.controller_metadata_provider', ControllerMetadataProvider::class)
            ->setAutoconfigured(true)
            ->setPublic(true)
            ->setArguments([
                $this->createMock(RouterInterface::class),
                [],
                [],
            ]);

        $extension = new PurgatoryExtension();
        $extension->load([
            'sofascore_purgatory' => [
                'route_ignore_patterns' => ['/^_profiler/'],
            ],
        ], $container);

        self::assertTrue($container->has('sofascore.purgatory.controller_metadata_provider'));

        $definition = $container->getDefinition('sofascore.purgatory.controller_metadata_provider');
        self::assertSame(ControllerMetadataProvider::class, $definition->getClass());

        $ignoredPatterns = $definition->getArgument(2);
        self::assertCount(1, $ignoredPatterns);
        self::assertSame('/^_profiler/', $ignoredPatterns[0]);
    }

    public function testSubscriptionResolverIsTagged(): void
    {
        $container = new ContainerBuilder();

        $container->register(EmbeddableResolver::class)
            ->setAutoconfigured(true)
            ->setPublic(true);

        $container->register(MethodResolver::class)
            ->setAutoconfigured(true)
            ->setPublic(true);

        $container->register(PropertyResolver::class)
            ->setAutoconfigured(true)
            ->setPublic(true);

        $container->register(AssociationResolver::class)
            ->setAutoconfigured(true)
            ->setPublic(true);

        $extension = new PurgatoryExtension();
        $extension->load([], $container);

        $container->compile();

        self::assertTrue($container->getDefinition(EmbeddableResolver::class)->hasTag('purgatory.subscription_resolver'));
        self::assertTrue($container->getDefinition(MethodResolver::class)->hasTag('purgatory.subscription_resolver'));
        self::assertTrue($container->getDefinition(PropertyResolver::class)->hasTag('purgatory.subscription_resolver'));
        self::assertTrue($container->getDefinition(AssociationResolver::class)->hasTag('purgatory.subscription_resolver'));
    }

    public function testTargetResolverIsTagged(): void
    {
        $container = new ContainerBuilder();

        $container->register(ForPropertiesResolver::class)
            ->setAutoconfigured(true)
            ->setPublic(true);

        $container->register(ForGroupsResolver::class)
            ->setAutoconfigured(true)
            ->setPublic(true);

        $extension = new PurgatoryExtension();
        $extension->load([], $container);

        $container->compile();

        self::assertTrue($container->getDefinition(ForPropertiesResolver::class)->hasTag('purgatory.target_resolver'));
        self::assertTrue($container->getDefinition(ForGroupsResolver::class)->hasTag('purgatory.target_resolver'));
    }

    public function testRouteProviderIsTagged(): void
    {
        $container = new ContainerBuilder();

        $container->register(CreatedEntityRouteProvider::class)
            ->setAutoconfigured(true)
            ->setPublic(true);

        $container->register(RemovedEntityRouteProvider::class)
            ->setAutoconfigured(true)
            ->setPublic(true);

        $container->register(UpdatedEntityRouteProvider::class)
            ->setAutoconfigured(true)
            ->setPublic(true);

        $extension = new PurgatoryExtension();
        $extension->load([], $container);

        $container->compile();

        self::assertTrue($container->getDefinition(CreatedEntityRouteProvider::class)->hasTag('purgatory.route_provider'));
        self::assertTrue($container->getDefinition(RemovedEntityRouteProvider::class)->hasTag('purgatory.route_provider'));
        self::assertTrue($container->getDefinition(UpdatedEntityRouteProvider::class)->hasTag('purgatory.route_provider'));
    }

    public function testRouteParamValuesResolverIsTagged(): void
    {
        $container = new ContainerBuilder();

        $container->register(CompoundValuesResolver::class)
            ->setAutoconfigured(true)
            ->setPublic(true);

        $container->register(EnumValuesResolver::class)
            ->setAutoconfigured(true)
            ->setPublic(true);

        $container->register(PropertyValuesResolver::class)
            ->setAutoconfigured(true)
            ->setPublic(true);

        $container->register(RawValuesResolver::class)
            ->setAutoconfigured(true)
            ->setPublic(true);

        $extension = new PurgatoryExtension();
        $extension->load([], $container);

        $container->compile();

        self::assertTrue($container->getDefinition(CompoundValuesResolver::class)->hasTag('purgatory.route_param_value_resolver'));
        self::assertTrue($container->getDefinition(EnumValuesResolver::class)->hasTag('purgatory.route_param_value_resolver'));
        self::assertTrue($container->getDefinition(PropertyValuesResolver::class)->hasTag('purgatory.route_param_value_resolver'));
        self::assertTrue($container->getDefinition(RawValuesResolver::class)->hasTag('purgatory.route_param_value_resolver'));
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
