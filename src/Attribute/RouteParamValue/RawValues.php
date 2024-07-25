<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Attribute\RouteParamValue;

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

    public static function type(): string
    {
        return 'raw';
    }
}
