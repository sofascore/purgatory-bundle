<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Functional\TestApplication\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
abstract class Vehicle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public int $id;

    #[ORM\Column]
    public string $name;
}
