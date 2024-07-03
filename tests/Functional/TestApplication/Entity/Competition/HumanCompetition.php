<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Functional\TestApplication\Entity\Competition;

use Doctrine\ORM\Mapping as ORM;
use Sofascore\PurgatoryBundle2\Tests\Functional\TestApplication\Entity\Person;

#[ORM\Entity]
class HumanCompetition extends Competition
{
    #[ORM\ManyToOne]
    #[ORM\JoinColumn]
    public Person $winner;
}
