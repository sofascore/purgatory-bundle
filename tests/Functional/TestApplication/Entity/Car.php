<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Functional\TestApplication\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Car extends Vehicle
{
    #[ORM\ManyToOne(
        targetEntity: Person::class,
        inversedBy: 'cars',
    )]
    #[ORM\JoinColumn(nullable: true)]
    public ?Person $owner = null;
}
