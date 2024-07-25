<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Functional\TestApplication\Entity\Competition;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class AnimalCompetition extends Competition
{
    #[ORM\Column(nullable: true)]
    public ?int $numberOfPets = null;
}
