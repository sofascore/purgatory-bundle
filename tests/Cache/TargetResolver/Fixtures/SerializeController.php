<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Cache\TargetResolver\Fixtures;

use Symfony\Component\HttpKernel\Attribute\Serialize;

final class SerializeController
{
    #[Serialize(context: ['groups' => 'group1'])]
    public function singleGroup(): void
    {
    }

    #[Serialize(context: ['groups' => ['group1', 'group2']])]
    public function multipleGroups(): void
    {
    }

    public function withoutSerialize(): void
    {
    }

    #[Serialize]
    public function withoutGroups(): void
    {
    }

    #[Serialize(context: ['groups' => []])]
    public function emptyGroups(): void
    {
    }

    #[Serialize(context: ['groups' => ''])]
    public function emptyStringGroup(): void
    {
    }

    #[Serialize(context: ['groups' => 123])]
    public function nonStringGroup(): void
    {
    }

    #[Serialize(context: ['groups' => ['group1', 123]])]
    public function nonStringGroups(): void
    {
    }

    #[Serialize(context: ['groups' => ['foo' => 'group1']])]
    public function nonListGroups(): void
    {
    }
}
