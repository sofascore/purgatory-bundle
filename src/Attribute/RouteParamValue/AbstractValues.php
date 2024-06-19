<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Attribute\RouteParamValue;

abstract class AbstractValues implements ValuesInterface
{
    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'type' => static::class,
            'values' => $this->getValues(),
        ];
    }
}
