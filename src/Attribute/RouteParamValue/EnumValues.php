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
        private readonly string $enum,
    ) {
        if (!is_a($this->enum, \BackedEnum::class, true)) {
            throw new InvalidArgumentException('The argument must be a backed enum.');
        }
    }

    /**
     * @return list<class-string<\BackedEnum>>
     */
    public function getValues(): array
    {
        return [$this->enum];
    }

    public static function type(): string
    {
        return 'enum';
    }
}
