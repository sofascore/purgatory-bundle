<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Application;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\RequiresPhp;
use Sofascore\PurgatoryBundle\Test\InteractsWithPurgatory;
use Sofascore\PurgatoryBundle\Tests\Functional\AbstractKernelTestCase;
use Sofascore\PurgatoryBundle\Tests\Functional\Php85TestApplication\Controller\PlantController;
use Sofascore\PurgatoryBundle\Tests\Functional\Php85TestApplication\Entity\Garden;
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

    /**
     * @see PlantController::gardenPlantsAction
     */
    public function testIfWithClosureOnInverseRelation(): void
    {
        $publicGarden = new Garden(public: true);
        $privateGarden = new Garden(public: false);
        $this->entityManager->persist($publicGarden);
        $this->entityManager->persist($privateGarden);
        $this->entityManager->flush();
        self::clearPurger();

        $plant = new Plant(waterLevel: 1, garden: $publicGarden);
        $this->entityManager->persist($plant);
        $this->entityManager->flush();

        self::assertUrlIsPurged('/gardens/'.$publicGarden->getId().'/plants');
        self::clearPurger();

        $plant->setWaterLevel(2);
        $this->entityManager->flush();

        self::assertUrlIsPurged('/gardens/'.$publicGarden->getId().'/plants');
        self::clearPurger();

        $plant = new Plant(waterLevel: 1, garden: $privateGarden);
        $this->entityManager->persist($plant);
        $this->entityManager->flush();

        self::assertUrlIsNotPurged('/gardens/'.$privateGarden->getId().'/plants');
        self::assertUrlIsNotPurged('/gardens/'.$publicGarden->getId().'/plants');
        self::clearPurger();

        // a plant without a garden has nothing to purge and must not break the closure
        $plant = new Plant(waterLevel: 1);
        $this->entityManager->persist($plant);
        $this->entityManager->flush();

        self::assertUrlIsNotPurged('/gardens/'.$publicGarden->getId().'/plants');
        self::assertUrlIsNotPurged('/gardens/'.$privateGarden->getId().'/plants');
    }

    /**
     * @see PlantController::gardenPlantsAction
     */
    public function testIfWithClosureOnInverseOneToOneRelation(): void
    {
        $publicBestPlant = new Plant(waterLevel: 1);
        $privateBestPlant = new Plant(waterLevel: 1);
        $ordinaryPlant = new Plant(waterLevel: 1);

        $publicGarden = new Garden(public: true);
        $publicGarden->setBestPlant($publicBestPlant);
        $privateGarden = new Garden(public: false);
        $privateGarden->setBestPlant($privateBestPlant);

        $this->entityManager->persist($publicBestPlant);
        $this->entityManager->persist($privateBestPlant);
        $this->entityManager->persist($ordinaryPlant);
        $this->entityManager->persist($publicGarden);
        $this->entityManager->persist($privateGarden);
        $this->entityManager->flush();

        self::assertUrlIsPurged('/gardens/'.$publicGarden->getId().'/plants');
        self::assertUrlIsNotPurged('/gardens/'.$privateGarden->getId().'/plants');
        self::clearPurger();

        $publicBestPlant->setWaterLevel(2);
        $this->entityManager->flush();

        self::assertUrlIsPurged('/gardens/'.$publicGarden->getId().'/plants');
        self::clearPurger();

        $privateBestPlant->setWaterLevel(2);
        $this->entityManager->flush();

        self::assertUrlIsNotPurged('/gardens/'.$privateGarden->getId().'/plants');
        self::assertUrlIsNotPurged('/gardens/'.$publicGarden->getId().'/plants');
        self::clearPurger();

        $ordinaryPlant->setWaterLevel(2);
        $this->entityManager->flush();

        self::assertUrlIsNotPurged('/gardens/'.$publicGarden->getId().'/plants');
        self::assertUrlIsNotPurged('/gardens/'.$privateGarden->getId().'/plants');
    }
}
