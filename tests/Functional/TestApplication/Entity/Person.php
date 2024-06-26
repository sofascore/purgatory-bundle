<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Functional\TestApplication\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Person
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public int $id;

    #[ORM\Column]
    public string $firstName;

    #[ORM\Column]
    public string $lastName;

    #[ORM\Column]
    public string $gender;

    #[ORM\Column(nullable: true)]
    public ?int $numberOfPets = null;

    #[ORM\OneToMany(
        targetEntity: Animal::class,
        mappedBy: 'owner',
        cascade: ['PERSIST'],
    )]
    public Collection $pets;

    public function __construct()
    {
        $this->pets = new ArrayCollection();
    }

    /**
     * @return int[]
     */
    public function getPetsIds(): array
    {
        return $this->pets->map(
            static fn (Animal $animal): int => $animal->id,
        )->toArray();
    }
}
