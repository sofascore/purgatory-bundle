<?php
declare(strict_types=1);


namespace AnnotationReader\Fixtures;


use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Entity;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @Entity
 * @codeCoverageIgnore
 */
class Entity1
{
    /**
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue()
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

    /**
     * @Groups({"test"})
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}