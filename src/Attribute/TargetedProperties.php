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
    public readonly array $properties;

    public function __construct(
        string $property,
        string ...$properties,
    ) {
        $this->properties = [$property, ...array_values($properties)];
    }
}
