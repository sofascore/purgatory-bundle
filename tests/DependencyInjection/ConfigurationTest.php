<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle2\DependencyInjection\Configuration;
use Sofascore\PurgatoryBundle2\DependencyInjection\PurgatoryExtension;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

#[CoversClass(Configuration::class)]
final class ConfigurationTest extends TestCase
{
    public function testDefaultConfig(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), ['sofascore_purgatory' => []]);

        self::assertSame([
            'mapping_paths' => [],
            'route_ignore_patterns' => [],
            'doctrine_middleware_priority' => null,
            'doctrine_event_listener_priorities' => [
                'preRemove' => null,
                'postPersist' => null,
                'postUpdate' => null,
            ],
            'purger' => [
                'name' => null,
                'host' => null,
            ],
            'messenger' => [
                'transport' => null,
                'bus' => null,
                'batch_size' => null,
            ],
        ], $config);
    }

    public function testPurgerHostValidation(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('A host must be provided when using the Symfony purger.');

        (new Processor())->processConfiguration(new Configuration(), [
            'sofascore_purgatory' => [
                'purger' => [
                    'name' => 'symfony',
                ],
            ],
        ]);
    }

    public function testMessengerBusWithoutTransportValidation(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Cannot set the messenger bus without defining the transport.');

        (new Processor())->processConfiguration(new Configuration(), [
            'sofascore_purgatory' => [
                'messenger' => [
                    'bus' => 'some_id',
                ],
            ],
        ]);
    }

    public function testMessengerBatchSizeWithoutTransportValidation(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Cannot set the batch size without defining the transport.');

        (new Processor())->processConfiguration(new Configuration(), [
            'sofascore_purgatory' => [
                'messenger' => [
                    'batch_size' => 1,
                ],
            ],
        ]);
    }

    #[TestWith([0])]
    #[TestWith([-1])]
    public function testMessengerBatchSizeGreaterThanZeroValidation(int $batchSize): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The batch size must be a number greater than 0.');

        (new Processor())->processConfiguration(new Configuration(), [
            'sofascore_purgatory' => [
                'messenger' => [
                    'transport' => 'foo',
                    'batch_size' => $batchSize,
                ],
            ],
        ]);
    }

    public function testDoctrineListenerPrioritiesConifgurationForSingleValue(): void
    {
        $configuration = (new Processor())->processConfiguration(new Configuration(), [
            'sofascore_purgatory' => [
                'doctrine_event_listener_priorities' => 100,
            ],
        ]);

        self::assertSame(100, $configuration['doctrine_event_listener_priorities']['preRemove']);
        self::assertSame(100, $configuration['doctrine_event_listener_priorities']['postPersist']);
        self::assertSame(100, $configuration['doctrine_event_listener_priorities']['postUpdate']);
    }

    #[DataProvider('provideXMLCases')]
    public function testXMLConfiguration(string $file, array $expectedConfig): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new PurgatoryExtension());
        $locator = new FileLocator(__DIR__.'/Fixtures/xml');

        $xmlFileLoader = new XmlFileLoader($container, $locator);
        $xmlFileLoader->load($file);

        $config = (new Processor())->processConfiguration(new Configuration(), $container->getExtensionConfig('sofascore_purgatory'));

        self::assertSame($expectedConfig, $config);
    }

    public static function provideXMLCases(): iterable
    {
        yield 'all' => [
            'all.xml',
            [
                'doctrine_middleware_priority' => 5,
                'doctrine_event_listener_priorities' => [
                    'preRemove' => 10,
                    'postPersist' => 20,
                    'postUpdate' => 30,
                ],
                'purger' => [
                    'name' => 'symfony',
                    'host' => 'localhost',
                ],
                'messenger' => [
                    'transport' => 'async',
                    'bus' => 'command_bus',
                    'batch_size' => 100,
                ],
                'mapping_paths' => [
                    '%kernel.project_dir%/one.yaml',
                    '%kernel.project_dir%/two.yaml',
                ],
                'route_ignore_patterns' => [
                    0 => '/^_profiler/',
                    1 => '/^_wdt/',
                ],
            ],
        ];
        yield 'short listener' => [
            'short_listener.xml',
            [
                'doctrine_event_listener_priorities' => [
                    'preRemove' => 10,
                    'postPersist' => 10,
                    'postUpdate' => 10,
                ],
                'mapping_paths' => [],
                'route_ignore_patterns' => [],
                'doctrine_middleware_priority' => null,
                'purger' => [
                    'name' => null,
                    'host' => null,
                ],
                'messenger' => [
                    'transport' => null,
                    'bus' => null,
                    'batch_size' => null,
                ],
            ],
        ];
    }
}
