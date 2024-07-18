<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Functional\TestApplication\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Plane extends Vehicle
{
    #[ORM\Column]
    public int $numberOfEngines;
}
