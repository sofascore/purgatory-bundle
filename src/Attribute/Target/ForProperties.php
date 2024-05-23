<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Attribute\Target;

final class ForProperties implements TargetInterface
{
    /**
     * @param list<string> $properties
     */
    public function __construct(
        public readonly array $properties,
    ) {
    }
}
