<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Attribute\RouteParamValue;

interface ValuesInterface
{
    /**
     * @return array{type: string, values: list<mixed>}
     */
    public function toArray(): array;

    public static function type(): string;
}
