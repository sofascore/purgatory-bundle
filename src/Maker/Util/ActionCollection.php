<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Maker\Util;

final class ActionCollection
{
    /**
     * @param non-empty-list<'Create'|'Update'|'Delete'> $actions
     */
    public function __construct(
        private readonly array $actions,
    ) {
    }

    public function __toString(): string
    {
        if (1 === \count($this->actions)) {
            return "Action::{$this->actions[0]} === \$action";
        }

        $haystack = implode(
            ', ',
            array_map(static fn (string $name): string => "Action::$name", $this->actions),
        );

        return "\in_array(\$action, [$haystack], true)";
    }
}
