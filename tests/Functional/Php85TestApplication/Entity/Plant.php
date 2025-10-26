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

    public function __construct(int $waterLevel)
    {
        $this->waterLevel = $waterLevel;
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


}
