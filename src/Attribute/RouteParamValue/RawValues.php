<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Attribute\RouteParamValue;

final class RawValues extends AbstractValues
{
    /** @var non-empty-list<?scalar> */
    private readonly array $values;

    public function __construct(
        int|float|string|bool|null $value,
        int|float|string|bool|null ...$values,
    ) {
        $this->values = [$value, ...array_values($values)];
    }

    /**
     * @return non-empty-list<?scalar>
     */
    public function getValues(): array
    {
        return $this->values;
    }

    public static function type(): string
    {
        return 'raw';
    }
}
