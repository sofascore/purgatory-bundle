<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle2\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

#[CoversClass(Configuration::class)]
final class ConfigurationTest extends TestCase
{
    public function testDefaultConfig(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), ['sofascore_purgatory' => []]);

        self::assertSame([
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
}
