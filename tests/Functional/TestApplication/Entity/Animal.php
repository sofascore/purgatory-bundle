<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Functional\TestApplication\Entity;

use Doctrine\ORM\Mapping as ORM;
use Sofascore\PurgatoryBundle2\Attribute\TargetedProperties;
use Symfony\Component\Serializer\Annotation\Groups as AnnotationGroups;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
class Animal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public int $id;

    #[ORM\Column]
    #[Groups('common')]
    #[AnnotationGroups('common')]
    public string $name;

    #[ORM\Embedded(class: Measurements::class)]
    public Measurements $measurements;

    #[ORM\ManyToOne(
        targetEntity: Person::class,
        inversedBy: 'pets',
    )]
    #[ORM\JoinColumn(nullable: false)]
    public Person $owner;

    public function __construct()
    {
        $this->measurements = new Measurements();
    }

    #[Groups('common')]
    #[AnnotationGroups('common')]
    #[TargetedProperties('measurements')]
    public function isGoodBoy(): bool
    {
        return ($this->measurements->height ?? 0) + ($this->measurements->weight ?? 0) > 100;
    }
}
