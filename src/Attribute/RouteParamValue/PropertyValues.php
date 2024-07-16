<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Attribute\RouteParamValue;

final class PropertyValues extends AbstractValues implements InverseValuesAwareInterface
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

    public function buildInverseValuesFor(string $association): ValuesInterface
    {
        return new self(
            ...array_map(
                static fn (string $property): string => sprintf('%s.%s', $association, $property),
                $this->properties,
            ),
        );
    }

    public static function type(): string
    {
        return 'property';
    }
}
