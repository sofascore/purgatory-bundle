<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Functional\EntityChangeListener\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class DummyParent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Dummy $dummy;

    public function __construct(Dummy $dummy)
    {
        $this->dummy = $dummy;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDummy(): Dummy
    {
        return $this->dummy;
    }

    public function setDummy(Dummy $dummy): static
    {
        $this->dummy = $dummy;

        return $this;
    }
}
