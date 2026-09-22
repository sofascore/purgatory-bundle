<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Functional\Php85TestApplication\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Plant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\Column]
    private int $waterLevel;

    #[ORM\ManyToOne(
        targetEntity: Garden::class,
        inversedBy: 'plants',
    )]
    private ?Garden $garden;

    #[ORM\OneToOne(
        targetEntity: Garden::class,
        mappedBy: 'bestPlant',
    )]
    private ?Garden $bestInGarden = null;

    public function __construct(int $waterLevel, ?Garden $garden = null)
    {
        $this->waterLevel = $waterLevel;
        $this->garden = $garden;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getWaterLevel(): int
    {
        return $this->waterLevel;
    }

    public function setWaterLevel(int $waterLevel): void
    {
        $this->waterLevel = $waterLevel;
    }

    public function getGarden(): ?Garden
    {
        return $this->garden;
    }

    public function getBestInGarden(): ?Garden
    {
        return $this->bestInGarden;
    }

    public function setBestInGarden(?Garden $bestInGarden): void
    {
        $this->bestInGarden = $bestInGarden;
    }
}
