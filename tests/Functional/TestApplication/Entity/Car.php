<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Functional\TestApplication\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Car
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public int $id;

    #[ORM\Column]
    public string $name;

    #[ORM\ManyToOne(
        targetEntity: Person::class,
        inversedBy: 'cars',
    )]
    #[ORM\JoinColumn(nullable: true)]
    public ?Person $owner = null;
}
