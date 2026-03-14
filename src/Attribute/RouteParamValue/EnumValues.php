<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Attribute\RouteParamValue;

use Sofascore\PurgatoryBundle\Exception\InvalidArgumentException;

final class EnumValues extends AbstractValues
{
    /**
     * @param class-string<\BackedEnum> $enum
     */
    public function __construct(
        public readonly string $enum,
    ) {
        if (!is_a($this->enum, \BackedEnum::class, true)) {
            throw new InvalidArgumentException('The argument must be a backed enum.');
        }
    }

    /**
     * @return non-empty-list<class-string<\BackedEnum>>
     */
    protected function getValues(): array
    {
        return [$this->enum];
    }

    public static function type(): string
    {
        return 'enum';
    }
}
