<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Attribute\Target;

final class ForGroups implements TargetInterface
{
    /**
     * @var non-empty-list<string>
     */
    public readonly array $groups;

    /**
     * @param string|non-empty-list<string> $group
     */
    public function __construct(string|array $group)
    {
        $this->groups = (array) $group;
    }
}
