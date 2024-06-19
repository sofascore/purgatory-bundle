<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Attribute\RouteParamValue;

final class PropertyValues extends AbstractValues
{
    /** @var list<string> */
    private readonly array $properties;

    public function __construct(
        string ...$properties,
    ) {
        $this->properties = array_values($properties);
    }

    /**
     * @return list<string>
     */
    public function getValues(): array
    {
        return $this->properties;
    }
}
