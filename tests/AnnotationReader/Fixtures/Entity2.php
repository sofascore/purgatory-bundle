<?php

declare(strict_types=1);


namespace Sofascore\PurgatoryBundle\Tests\AnnotationReader\Fixtures;


use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Entity;

/**
 * @Entity
 * @codeCoverageIgnore
 */
class Entity2
{
    /**
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue()
     * @ORM\Id()
     */
    private int $id;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $count = null;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private ?string $priority = null;

    /**
     * @ORM\ManyToOne(targetEntity="AnnotationReader\Fixtures\Entity1")
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
     */
    private ?Entity1 $entity1 = null;


    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getCount(): ?int
    {
        return $this->count;
    }

    public function setCount(?int $count): void
    {
        $this->count = $count;
    }

    public function getPriority(): ?string
    {
        return $this->priority;
    }

    public function setPriority(?string $priority): void
    {
        $this->priority = $priority;
    }

    public function getEntity1(): ?Entity1
    {
        return $this->entity1;
    }

    public function setEntity1(?Entity1 $entity1): void
    {
        $this->entity1 = $entity1;
    }
}
