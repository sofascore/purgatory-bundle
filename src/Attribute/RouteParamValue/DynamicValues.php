<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Attribute\RouteParamValue;

final class DynamicValues extends AbstractValues implements InverseValuesAwareInterface
{
    /**
     * @param string $alias Alias defined in {@see AsPurgatoryResolver} attribute
     */
    public function __construct(
        private readonly string $alias,
        private readonly ?string $method = null,
        private readonly ?string $arg = null,
    ) {
    }

    /**
     * @return list<?string>
     */
    public function getValues(): array
    {
        return [$this->alias, $this->method, $this->arg];
    }

    public function buildInverseValuesFor(string $association): ValuesInterface
    {
        return new self(
            alias: $this->alias,
            method: $this->method,
            arg: null !== $this->arg ? sprintf('%s.%s', $association, $this->arg) : $association,
        );
    }
}
