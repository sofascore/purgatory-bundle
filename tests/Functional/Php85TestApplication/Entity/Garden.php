<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Functional\Php85TestApplication\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Garden
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\Column]
    private bool $public;

    /**
     * @var Collection<int, Plant>
     */
    #[ORM\OneToMany(
        targetEntity: Plant::class,
        mappedBy: 'garden',
    )]
    private Collection $plants;

    #[ORM\OneToOne(
        targetEntity: Plant::class,
        inversedBy: 'bestInGarden',
    )]
    private ?Plant $bestPlant = null;

    public function __construct(bool $public)
    {
        $this->public = $public;
        $this->plants = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function isPublic(): bool
    {
        return $this->public;
    }

    /**
     * @return Collection<int, Plant>
     */
    public function getPlants(): Collection
    {
        return $this->plants;
    }

    public function getBestPlant(): ?Plant
    {
        return $this->bestPlant;
    }

    public function setBestPlant(?Plant $bestPlant): void
    {
        $this->bestPlant?->setBestInGarden(null);
        $bestPlant?->setBestInGarden($this);
        $this->bestPlant = $bestPlant;
    }
}
