<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle;

use Doctrine\ORM\Events as DoctrineEvents;
use Sofascore\PurgatoryBundle\Attribute\AsExpressionLanguageFunction;
use Sofascore\PurgatoryBundle\Attribute\AsRouteParamService;
use Sofascore\PurgatoryBundle\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle\Cache\PropertyResolver\InverseValuesBuilder\InverseValuesBuilderInterface;
use Sofascore\PurgatoryBundle\Cache\PropertyResolver\SubscriptionResolverInterface;
use Sofascore\PurgatoryBundle\Cache\TargetResolver\TargetResolverInterface;
use Sofascore\PurgatoryBundle\DependencyInjection\BundleExtensionWrapper;
use Sofascore\PurgatoryBundle\DependencyInjection\ControllerClassMapCompilerPass;
use Sofascore\PurgatoryBundle\DependencyInjection\RegisterExpressionLanguageProvidersCompilerPass;
use Sofascore\PurgatoryBundle\DependencyInjection\RegisterPurgerCompilerPass;
use Sofascore\PurgatoryBundle\DependencyInjection\RegisterRouteParamServicesCompilerPass;
use Sofascore\PurgatoryBundle\Exception\LogicException;
use Sofascore\PurgatoryBundle\Exception\RuntimeException;
use Sofascore\PurgatoryBundle\Purger\Messenger\PurgeMessage;
use Sofascore\PurgatoryBundle\RouteParamValueResolver\ValuesResolverInterface;
use Sofascore\PurgatoryBundle\RouteProvider\RouteProviderInterface;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Yaml\Parser as YamlParser;

final class PurgatoryBundle extends AbstractBundle implements CompilerPassInterface
{
    private ?BundleExtensionWrapper $wrappedExtension = null;

    public function build(ContainerBuilder $container): void
    {
        // @TODO Remove when Symfony <8.1 support is dropped, {@see https://github.com/symfony/symfony/pull/62800}
        if (Kernel::VERSION_ID < 80100) {
            $container->addCompilerPass($this, PassConfig::TYPE_BEFORE_OPTIMIZATION, -1000);
        }

        $container->addCompilerPass(new ControllerClassMapCompilerPass());
        $container->addCompilerPass(new RegisterExpressionLanguageProvidersCompilerPass());
        $container->addCompilerPass(new RegisterPurgerCompilerPass());
        $container->addCompilerPass(new RegisterRouteParamServicesCompilerPass());
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->import(\dirname(__DIR__).'/config/definition.php');
    }

    public function prependExtension(ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        /** @var ConfigurationInterface $configuration */
        $configuration = $this->getContainerExtension()->getConfiguration([], $container);
        /** @var array{messenger: array{transport: ?string}} $config */
        $config = (new Processor())->processConfiguration(
            $configuration,
            $container->getExtensionConfig($this->extensionAlias),
        );

        if (null !== $transport = $config['messenger']['transport']) {
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
     * @param array<array-key, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__).'/config'));
        $loader->load('services.php');

        if ($config['profiler_integration']) {
            $loader->load('services_debug.php');
        }

        $container->registerAttributeForAutoconfiguration(
            PurgeOn::class,
            static function (ChildDefinition $definition, PurgeOn $attribute, \ReflectionClass|\ReflectionMethod $reflection): void {
                $definition->addTag(
                    name: 'purgatory.purge_on',
                    attributes: [
                        'class' => $reflection instanceof \ReflectionMethod ? $reflection->class : $reflection->name,
                    ],
                );
            },
        );

        $container->registerAttributeForAutoconfiguration(
            AsRouteParamService::class,
            static function (ChildDefinition $definition, AsRouteParamService $attribute, \ReflectionClass|\ReflectionMethod $reflection): void {
                $definition->addTag(
                    name: 'purgatory.route_parameter_service',
                    attributes: [
                        'alias' => $attribute->alias,
                        'method' => $reflection instanceof \ReflectionMethod
                            ? $reflection->name
                            : ($reflection->hasMethod('__invoke') ? '__invoke'
                                : throw new RuntimeException(\sprintf('Invalid route parameter service, the method "%s::__invoke()" does not exist.', $reflection->name))),
                    ],
                );
            },
        );

        $container->registerAttributeForAutoconfiguration(
            AsExpressionLanguageFunction::class,
            static function (ChildDefinition $definition, AsExpressionLanguageFunction $attribute, \ReflectionClass|\ReflectionMethod $reflection): void {
                $definition->addTag(
                    name: 'purgatory.expression_language_function',
                    attributes: [
                        'function' => $attribute->functionName,
                        'method' => $reflection instanceof \ReflectionMethod
                            ? $reflection->name
                            : ($reflection->hasMethod('__invoke') ? '__invoke'
                                : throw new RuntimeException(\sprintf('Invalid expression language function, the method "%s::__invoke()" does not exist.', $reflection->name))),
                    ],
                );
            },
        );

        /** @var array{name: ?string, hosts: list<string>, http_client: ?string} $purgerConfig */
        $purgerConfig = $config['purger'];
        $container->setParameter('.sofascore.purgatory.purger.name', $purgerConfig['name']);
        $container->setParameter('.sofascore.purgatory.purger.hosts', $purgerConfig['hosts']);

        if (null !== $purgerConfig['http_client']) {
            $container->getDefinition('sofascore.purgatory.purger.varnish')
                ->replaceArgument(0, new Reference($purgerConfig['http_client']));
        }

        /** @var list<string> $mappingPaths */
        $mappingPaths = $config['mapping_paths'];
        if ($files = iterator_to_array($this->registerMappingFiles($container, $mappingPaths), false)) {
            if (!class_exists(YamlParser::class)) {
                throw new LogicException('You cannot use YAML configuration because the Symfony Yaml component is not installed. Try running "composer require symfony/yaml".');
            }

            $container->getDefinition('sofascore.purgatory.route_metadata_provider.yaml')
                ->replaceArgument(1, $files);
        } else {
            $container->removeDefinition('sofascore.purgatory.route_metadata_provider.yaml');
        }

        $container->getDefinition('sofascore.purgatory.route_metadata_provider.attribute')
            ->setArgument(2, $config['route_ignore_patterns']);

        /** @var array<DoctrineEvents::*, ?int> $doctrineEventListenerPriorities */
        $doctrineEventListenerPriorities = $config['doctrine_event_listener_priorities'];

        /** @var array{enabled: bool, priority: ?int} $doctrineMiddlewareConfig */
        $doctrineMiddlewareConfig = $config['doctrine_middleware'];
        if ($doctrineMiddlewareConfig['enabled']) {
            $container->getDefinition('sofascore.purgatory.doctrine_middleware')
                ->addTag(
                    name: 'doctrine.middleware',
                    attributes: null !== $doctrineMiddlewareConfig['priority'] ? ['priority' => $doctrineMiddlewareConfig['priority']] : [],
                );

            unset($doctrineEventListenerPriorities[DoctrineEvents::postFlush]);
        } else {
            $container->removeDefinition('sofascore.purgatory.doctrine_middleware');
        }

        $listenerDefinition = $container->getDefinition('sofascore.purgatory.entity_change_listener');
        foreach ($doctrineEventListenerPriorities as $event => $priority) {
            $listenerDefinition->addTag(
                name: 'doctrine.event_listener',
                attributes: ['event' => $event] + (null !== $priority ? ['priority' => $priority] : []),
            );
        }

        /** @var array{transport: ?string, bus: ?string, batch_size: ?positive-int} $messengerConfig */
        $messengerConfig = $config['messenger'];
        if (null !== $messengerConfig['transport']) {
            $container->setParameter('.sofascore.purgatory.purger.async_transport', $messengerConfig['transport']);
            if (null !== $messengerConfig['bus']) {
                $container->getDefinition('sofascore.purgatory.purger.async')
                    ->replaceArgument(0, new Reference($messengerConfig['bus']));
            }
            if (null !== $messengerConfig['batch_size']) {
                $container->getDefinition('sofascore.purgatory.purger.async')
                    ->setArgument(1, $messengerConfig['batch_size']);
            }
            $container->getDefinition('sofascore.purgatory.purge_message_handler')
                ->addTag(
                    name: 'messenger.message_handler',
                    attributes: null !== $messengerConfig['bus'] ? ['bus' => $messengerConfig['bus']] : [],
                );
        } else {
            $container->setParameter('.sofascore.purgatory.purger.async_transport', null);
            $container->removeDefinition('sofascore.purgatory.purger.async');
            $container->removeDefinition('sofascore.purgatory.purge_message_handler');
        }

        $container->registerForAutoconfiguration(SubscriptionResolverInterface::class)
            ->addTag('purgatory.subscription_resolver');

        $container->registerForAutoconfiguration(InverseValuesBuilderInterface::class)
            ->addTag('purgatory.inverse_values_builder');

        $container->registerForAutoconfiguration(TargetResolverInterface::class)
            ->addTag('purgatory.target_resolver');

        $container->registerForAutoconfiguration(RouteProviderInterface::class)
            ->addTag('purgatory.route_provider');

        $container->registerForAutoconfiguration(ValuesResolverInterface::class)
            ->addTag('purgatory.route_param_value_resolver');

        if (!class_exists(HttpClient::class)) {
            $container->removeDefinition('sofascore.purgatory.purger.varnish');
        }

        if (!class_exists(ExpressionLanguage::class)) {
            $container->removeDefinition('sofascore.purgatory.expression_language');
        }
    }

    /**
     * @param list<string> $mappingPaths
     *
     * @return \Generator<int, string>
     */
    private function registerMappingFiles(ContainerBuilder $container, array $mappingPaths): \Generator
    {
        $registerMappingFilesFromDir = static function (string $dir): iterable {
            foreach (Finder::create()->followLinks()->files()->in($dir)->name('/\.ya?ml$/')->sortByName() as $file) {
                yield $file->getRealPath();
            }
        };

        if ($container->hasParameter('.kernel.config_dir')) {
            /** @var string $configDir */
            $configDir = $container->getParameter('.kernel.config_dir');
        } else {
            /** @var string $projectDir */
            $projectDir = $container->getParameter('kernel.project_dir');
            $configDir = $projectDir.'/config';
        }

        if ($container->fileExists($dir = $configDir.'/purgatory', '/^$/')) {
            yield from $registerMappingFilesFromDir($dir);
        }

        foreach ($mappingPaths as $path) {
            if (is_dir($path)) {
                $container->addResource(new DirectoryResource($path, '/^$/'));
                yield from $registerMappingFilesFromDir($path);
            } elseif ($container->fileExists($path)) {
                yield $path;
            } else {
                throw new RuntimeException(\sprintf('Could not open file or directory "%s".', $path));
            }
        }
    }

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('cache.system')) {
            $container->removeDefinition('sofascore.purgatory.cache.expression_language');
        }

        if (!$container->hasDefinition('profiler') || !$container->hasDefinition('twig')) {
            $container->removeDefinition('sofascore.purgatory.data_collector');
            $container->removeDefinition('sofascore.purgatory.purger.traceable');
            $container->removeDefinition('sofascore.purgatory.purger.sync.traceable');
        }
    }

    /**
     * @internal
     *
     * @see https://github.com/symfony/symfony/pull/64955
     *
     * @return BundleExtensionWrapper
     */
    public function getContainerExtension(): ExtensionInterface
    {
        if (null === $this->wrappedExtension) {
            /** @var ExtensionInterface&ConfigurationExtensionInterface&PrependExtensionInterface $extension */
            $extension = parent::getContainerExtension();

            $this->wrappedExtension = new BundleExtensionWrapper($extension);
        }

        return $this->wrappedExtension;
    }
}
