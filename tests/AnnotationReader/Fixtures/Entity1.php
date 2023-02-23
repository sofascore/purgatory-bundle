<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\AnnotationReader\Fixtures;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Entity;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @Entity
 *
 * @codeCoverageIgnore
 */
class Entity1
{
    /**
     * @ORM\Column(type="integer")
     *
     * @ORM\GeneratedValue()
     *
     * @ORM\Id()
     */
    protected int $id;

    /**
     * @ORM\Column(type="string", nullable=false)
     */
    protected string $name;

    /**
     * @ORM\Column(type="boolean")
     */
    protected bool $enabled = false;

    /**
     * @ORM\Column(type="datetime_immutable")
     */
    protected \DateTime $createdAt;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    /**
     * @Groups({"test"})
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
