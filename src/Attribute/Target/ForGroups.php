<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Attribute\Target;

final class ForGroups implements TargetInterface
{
    /**
     * @var list<string>
     */
    public readonly array $groups;

    /**
     * @param string|list<string> $group
     */
    public function __construct(string|array $group)
    {
        $this->groups = (array) $group;
    }
}
