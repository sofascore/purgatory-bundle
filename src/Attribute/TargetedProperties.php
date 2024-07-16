<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Attribute;

/**
 * Used on methods to define which properties the method is using.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class TargetedProperties
{
    /** @var non-empty-list<string> */
    public readonly array $target;

    public function __construct(
        string $target,
        string ...$targets,
    ) {
        $this->target = [$target, ...array_values($targets)];
    }
}
