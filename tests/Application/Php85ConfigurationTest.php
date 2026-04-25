<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Application;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresFunction;
use PHPUnit\Framework\Attributes\RequiresPhp;
use Sofascore\PurgatoryBundle\Cache\Configuration\Configuration;
use Sofascore\PurgatoryBundle\Listener\Enum\Action;
use Sofascore\PurgatoryBundle\Tests\Functional\AbstractKernelTestCase;
use Sofascore\PurgatoryBundle\Tests\Functional\Php85TestApplication\Controller\PlantController;
use Sofascore\PurgatoryBundle\Tests\Functional\Php85TestApplication\Entity\Plant;

#[RequiresPhp('>= 8.5.0')]
#[RequiresFunction('\Opis\Closure\serialize')]
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
        $expectedIf = <<<'EOF'
            O:16:"Opis\Closure\Box":2:{i:0;i:1;i:1;a:1:{s:4:"info";a:4:{s:3:"key";s:32:"7de5a138e0501360b836ac5fe50fc543";s:6:"header";s:167:"namespace Sofascore\PurgatoryBundle\Tests\Functional\Php85TestApplication\Controller;
            use Sofascore\PurgatoryBundle\Tests\Functional\Php85TestApplication\Entity\Plant;";s:4:"body";s:98:"static function (Plant $plant): bool {
                        return 0 === $plant->getWaterLevel();
                    }";s:5:"flags";i:2;}}}
            EOF;

        /* @see PlantController::dryPlantsAction */
        yield [
            'entity' => Plant::class,
            'subscription' => [
                'routeName' => 'dry_plants_list',
                'if' => $expectedIf,
                'closureIf' => true,
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
