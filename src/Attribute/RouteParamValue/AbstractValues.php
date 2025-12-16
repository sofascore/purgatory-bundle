<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Attribute\RouteParamValue;

abstract class AbstractValues implements ValuesInterface
{
    /**
     * @return non-empty-list<?scalar>
     */
    abstract protected function getValues(): array;

    /**
     * {@inheritDoc}
     */
    final public function toArray(): array
    {
        return [
            'type' => static::type(),
            'values' => $this->getValues(),
        ];
    }
}
