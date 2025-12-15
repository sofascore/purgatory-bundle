<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Attribute\RouteParamValue;

final class PropertyValues extends AbstractValues
{
    /** @var non-empty-list<string> */
    private readonly array $properties;

    public function __construct(
        string $property,
        string ...$properties,
    ) {
        $this->properties = [$property, ...array_values($properties)];
    }

    /**
     * @return non-empty-list<string>
     */
    public function getValues(): array
    {
        return $this->properties;
    }

    public static function type(): string
    {
        return 'property';
    }
}
