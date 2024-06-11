<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
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
}
