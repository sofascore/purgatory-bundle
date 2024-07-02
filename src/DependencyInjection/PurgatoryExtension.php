<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\DependencyInjection;

use Doctrine\ORM\Events as DoctrineEvents;
use Sofascore\PurgatoryBundle2\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle2\Cache\PropertyResolver\SubscriptionResolverInterface;
use Sofascore\PurgatoryBundle2\Cache\TargetResolver\TargetResolverInterface;
use Sofascore\PurgatoryBundle2\Purger\Messenger\PurgeMessage;
use Sofascore\PurgatoryBundle2\RouteParamValueResolver\ValuesResolverInterface;
use Sofascore\PurgatoryBundle2\RouteProvider\RouteProviderInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

final class PurgatoryExtension extends ConfigurableExtension implements PrependExtensionInterface
{
    public function prepend(ContainerBuilder $container): void
    {
        /** @var array{messenger: array{transport: ?string}} $mergedConfig */
        $mergedConfig = $this->processConfiguration(
            new Configuration(),
            $container->getExtensionConfig($this->getAlias()),
        );

        if (null !== $transport = $mergedConfig['messenger']['transport']) {
            $container->prependExtensionConfig('framework', [
                'messenger' => [
                    'routing' => [
                        PurgeMessage::class => $transport,
                    ],
                ],
            ]);
        }
    }

    /**
     * @param array<array-key, mixed> $mergedConfig
     */
    protected function loadInternal(array $mergedConfig, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__, 2).'/config'));
        $loader->load('services.php');

        $container->registerAttributeForAutoconfiguration(
            PurgeOn::class,
            static function (ChildDefinition $definition, PurgeOn $attribute, \ReflectionClass|\ReflectionMethod $reflection): void {
                $definition->addTag(
                    name: 'purgatory2.purge_on',
                    attributes: [
                        'class' => $reflection instanceof \ReflectionMethod ? $reflection->class : $reflection->name,
                    ],
                );
            },
        );

        /** @var array{name: ?string, host: ?string} $purgerConfig */
        $purgerConfig = $mergedConfig['purger'];
        $container->setParameter('.sofascore.purgatory2.purger.name', $purgerConfig['name']);
        $container->setParameter('.sofascore.purgatory2.purger.host', $purgerConfig['host']);

        $container->getDefinition('sofascore.purgatory2.route_metadata_provider')
            ->setArgument(2, $mergedConfig['route_ignore_patterns']);

        $container->getDefinition('sofascore.purgatory2.doctrine_middleware')
            ->addTag(
                name: 'doctrine.middleware',
                attributes: null !== $mergedConfig['doctrine_middleware_priority']
                    ? ['priority' => $mergedConfig['doctrine_middleware_priority']]
                    : [],
            );

        $listenerDefinition = $container->getDefinition('sofascore.purgatory2.entity_change_listener');
        /**
         * @var DoctrineEvents::* $event
         * @var ?int              $priority
         */
        foreach ($mergedConfig['doctrine_event_listener_priorities'] as $event => $priority) {
            $listenerDefinition->addTag(
                name: 'doctrine.event_listener',
                attributes: ['event' => $event] + (null !== $priority ? ['priority' => $priority] : []),
            );
        }

        /** @var array{transport: ?string, bus: ?string, batch_size: ?positive-int} $messengerConfig */
        $messengerConfig = $mergedConfig['messenger'];
        if (null !== $messengerConfig['transport']) {
            if (null !== $messengerConfig['bus']) {
                $container->getDefinition('sofascore.purgatory2.purger.async')
                    ->replaceArgument(0, new Reference($messengerConfig['bus']));
            }
            if (null !== $messengerConfig['batch_size']) {
                $container->getDefinition('sofascore.purgatory2.purger.async')
                    ->setArgument(1, $messengerConfig['batch_size']);
            }
            $container->getDefinition('sofascore.purgatory2.purge_message_handler')
                ->addTag(
                    name: 'messenger.message_handler',
                    attributes: null !== $messengerConfig['bus'] ? ['bus' => $messengerConfig['bus']] : [],
                );
        } else {
            $container->removeDefinition('sofascore.purgatory2.purger.async');
            $container->removeDefinition('sofascore.purgatory2.purge_message_handler');
        }

        $container->registerForAutoconfiguration(SubscriptionResolverInterface::class)
            ->addTag('purgatory2.subscription_resolver');

        $container->registerForAutoconfiguration(TargetResolverInterface::class)
            ->addTag('purgatory2.target_resolver');

        $container->registerForAutoconfiguration(RouteProviderInterface::class)
            ->addTag('purgatory2.route_provider');

        $container->registerForAutoconfiguration(ValuesResolverInterface::class)
            ->addTag('purgatory2.route_param_value_resolver');

        if (!$container->hasDefinition('cache.system')) {
            $container->removeDefinition('sofascore.purgatory2.cache.expression_language');
        }
        if (!class_exists(ExpressionLanguage::class)) {
            $container->removeDefinition('sofascore.purgatory2.expression_language');
        }
    }

    public function getNamespace(): string
    {
        return 'http://sofascore.com/schema/dic/purgatory';
    }

    public function getXsdValidationBasePath(): string
    {
        return __DIR__.'/../../config/schema';
    }

    public function getAlias(): string
    {
        return 'sofascore_purgatory';
    }
}
