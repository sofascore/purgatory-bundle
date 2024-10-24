<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Attribute\RouteParamValue;

use Symfony\Component\HttpKernel\Kernel;

final class PropertyValues extends AbstractValues implements InverseValuesAwareInterface
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

    public function buildInverseValuesFor(string $association): ValuesInterface
    {
        return new self(
            ...array_map(
                static fn (string $property): string => \sprintf('%s%s.%s', $association, Kernel::MAJOR_VERSION > 5 ? '?' : '', $property),
                $this->properties,
            ),
        );
    }

    public static function type(): string
    {
        return 'property';
    }
}
