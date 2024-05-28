<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Attribute;

/**
 * Used on methods to define which properties the method is using.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class TargetedProperties
{
    /**
     * @param list<string> $target
     */
    public function __construct(
        public readonly array $target,
    ) {
    }
}
