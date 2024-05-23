<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Attribute\Target;

final class ForGroups implements TargetInterface
{
    /**
     * @param list<string> $groups
     */
    public function __construct(
        public readonly array $groups,
    ) {
    }
}
