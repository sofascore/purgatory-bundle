<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Attribute\RouteParamValue;

use Symfony\Component\HttpKernel\Kernel;

final class DynamicValues extends AbstractValues implements InverseValuesAwareInterface
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

    public function buildInverseValuesFor(string $association): ValuesInterface
    {
        return new self(
            alias: $this->alias,
            arg: null !== $this->arg ? sprintf('%s%s.%s', $association, Kernel::MAJOR_VERSION > 5 ? '?' : '', $this->arg) : $association,
        );
    }

    public static function type(): string
    {
        return 'dynamic';
    }
}
