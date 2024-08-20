<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Functional\TestApplication\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sofascore\PurgatoryBundle\Tests\Functional\TestApplication\Enum\Country;

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

    #[ORM\Column(type: Types::STRING, nullable: true, enumType: Country::class)]
    public ?Country $country = null;

    #[ORM\OneToMany(
        targetEntity: Animal::class,
        mappedBy: 'owner',
        cascade: ['PERSIST'],
    )]
    public Collection $pets;

    #[ORM\OneToMany(
        targetEntity: Car::class,
        mappedBy: 'owner',
        cascade: ['PERSIST'],
    )]
    public Collection $cars;

    #[ORM\OneToMany(
        targetEntity: Animal::class,
        mappedBy: 'veterinarian',
        cascade: ['PERSIST'],
    )]
    public Collection $animalPatients;

    public function __construct()
    {
        $this->pets = new ArrayCollection();
        $this->cars = new ArrayCollection();
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

    public function getAlpha2(): ?string
    {
        return $this->country?->value;
    }
}
