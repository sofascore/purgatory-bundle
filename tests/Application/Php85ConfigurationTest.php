<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Application;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhp;
use Sofascore\PurgatoryBundle\Cache\Configuration\Configuration;
use Sofascore\PurgatoryBundle\Listener\Enum\Action;
use Sofascore\PurgatoryBundle\Tests\Functional\AbstractKernelTestCase;
use Sofascore\PurgatoryBundle\Tests\Functional\Php85TestApplication\Controller\PlantController;
use Sofascore\PurgatoryBundle\Tests\Functional\Php85TestApplication\Entity\Plant;

#[RequiresPhp('>= 8.5.0')]
class Php85ConfigurationTest extends AbstractKernelTestCase
{
    private static ?Configuration $configuration;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::initializeApplication(['test_case' => 'Php85TestApplication', 'config' => 'app_config.yaml']);

        self::$configuration = self::getContainer()->get('sofascore.purgatory.configuration_loader')->load();

        self::ensureKernelShutdown();
    }

    public static function tearDownAfterClass(): void
    {
        self::$configuration = null;

        parent::tearDownAfterClass();
    }

    #[DataProvider('configurationProvider')]
    public function testConfiguration(string $entity, array $subscription): void
    {
        self::assertSubscriptionExists(
            key: $entity,
            subscription: $subscription,
        );
    }

    public static function configurationProvider(): iterable
    {
        /* @see PlantController::dryPlantsAction */
        yield [
            'entity' => Plant::class,
            'subscription' => [
                'routeName' => 'dry_plants_list',
                'if' => [
                    'classes' => '',
                    'objectMeta' => 0,
                    'prepared' => [
                        'Sofascore\PurgatoryBundle\Tests\Functional\Php85TestApplication\Controller\PlantController',
                        'dryPlantsAction()',
                        2,
                        0,
                        20,
                    ],
                    'mask' => 1,
                ],
                'actions' => [Action::Create],
            ],
        ];
    }

    private static function assertSubscriptionExists(string $key, array $subscription): void
    {
        self::assertTrue(
            condition: self::$configuration->has($key),
            message: \sprintf('Failed asserting that the configuration contains a subscription for "%s".', $key),
        );

        self::assertContains(
            needle: $subscription,
            haystack: self::$configuration->get($key),
            message: \sprintf('Failed asserting that the configuration contains the subscription "%s" for the key "%s".', json_encode($subscription), $key),
        );
    }
}
