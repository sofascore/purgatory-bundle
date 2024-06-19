<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Attribute\RouteParamValue;

final class RawValues extends AbstractValues
{
    /** @var list<?scalar> */
    private readonly array $values;

    public function __construct(
        int|float|string|bool|null ...$values,
    ) {
        $this->values = array_values($values);
    }

    /**
     * @return list<?scalar>
     */
    public function getValues(): array
    {
        return $this->values;
    }
}
