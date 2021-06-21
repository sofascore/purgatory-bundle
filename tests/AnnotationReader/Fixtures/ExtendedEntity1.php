<?php
declare(strict_types=1);


namespace AnnotationReader\Fixtures;


use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @codeCoverageIgnore
 */
class ExtendedEntity1 extends Entity1
{
    /**
     * @ORM\Column(type="text")
     * @Groups({"test"})
     */
    protected string $name;

    /**
     * @ORM\Column(type="string", nullable=false)
     */
    protected string $email;
}