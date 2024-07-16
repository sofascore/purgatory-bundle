<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Attribute\RouteParamValue;

interface ValuesInterface
{
    /**
     * @return list<mixed>
     */
    public function getValues(): array;

    /**
     * @return array{type: string, values: list<mixed>}
     */
    public function toArray(): array;

    public static function type(): string;
}
