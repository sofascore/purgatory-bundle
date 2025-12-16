<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Attribute\RouteParamValue;

final class DynamicValues extends AbstractValues
{
    /**
     * @param string $alias Alias defined in {@see AsRouteParamService} attribute
     */
    public function __construct(
        public readonly string $alias,
        public readonly ?string $arg = null,
    ) {
    }

    /**
     * @return non-empty-list<?string>
     */
    protected function getValues(): array
    {
        return [$this->alias, $this->arg];
    }

    public static function type(): string
    {
        return 'dynamic';
    }
}
