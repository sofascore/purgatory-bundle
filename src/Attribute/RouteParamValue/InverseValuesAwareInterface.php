<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Attribute\RouteParamValue;

interface InverseValuesAwareInterface extends ValuesInterface
{
    public function buildInverseValuesFor(string $association): ValuesInterface;
}
