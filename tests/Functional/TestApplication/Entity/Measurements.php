<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Functional\TestApplication\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class Measurements
{
    #[ORM\Column(nullable: true)]
    public ?int $height = null;

    #[ORM\Column(nullable: true)]
    public ?int $width = null;

    #[ORM\Column(nullable: true)]
    public ?int $weight = null;
}
