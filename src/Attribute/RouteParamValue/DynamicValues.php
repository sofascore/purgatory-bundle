<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Attribute\RouteParamValue;

final class DynamicValues extends AbstractValues
{
    /**
     * @param string $alias Alias defined in {@see AsRouteParamService} attribute
     */
    public function __construct(
        private readonly string $alias,
        private readonly ?string $arg = null,
    ) {
    }

    /**
     * @return list<?string>
     */
    public function getValues(): array
    {
        return [$this->alias, $this->arg];
    }

    public static function type(): string
    {
        return 'dynamic';
    }
}
