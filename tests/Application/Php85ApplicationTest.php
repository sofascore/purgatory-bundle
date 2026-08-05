<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Application;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\RequiresPhp;
use Sofascore\PurgatoryBundle\Test\InteractsWithPurgatory;
use Sofascore\PurgatoryBundle\Tests\Functional\AbstractKernelTestCase;
use Sofascore\PurgatoryBundle\Tests\Functional\Php85TestApplication\Controller\PlantController;
use Sofascore\PurgatoryBundle\Tests\Functional\Php85TestApplication\Entity\Plant;

#[RequiresPhp('>= 8.5.0')]
final class Php85ApplicationTest extends AbstractKernelTestCase
{
    use InteractsWithPurgatory;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::initializeApplication(['test_case' => 'Php85TestApplication', 'config' => 'app_config.yaml']);

        $this->entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
    }

    protected function tearDown(): void
    {
        unset($this->entityManager);

        parent::tearDown();
    }

    /**
     * @see PlantController::dryPlantsAction
     */
    public function testIfWithClosure(): void
    {
        $plant = new Plant(waterLevel: 0);
        $this->entityManager->persist($plant);
        $this->entityManager->flush();

        self::assertUrlIsPurged('/plants/dry');
        self::clearPurger();

        $plant = new Plant(waterLevel: 1);
        $this->entityManager->persist($plant);
        $this->entityManager->flush();

        self::assertUrlIsNotPurged('/plants/dry');
    }
}
