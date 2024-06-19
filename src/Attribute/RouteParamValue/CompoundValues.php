<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Attribute\RouteParamValue;

use Sofascore\PurgatoryBundle2\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle2\Exception\InvalidArgumentException;

final class CompoundValues extends AbstractValues
{
    /**
     * @var list<ValuesInterface>
     */
    private readonly array $values;

    /**
     * @param string|non-empty-list<string>|ValuesInterface ...$values
     */
    public function __construct(
        string|array|ValuesInterface ...$values,
    ) {
        /** @var list<ValuesInterface> $normalized */
        $normalized = [];

        foreach ($values as $value) {
            if ($value instanceof self) {
                throw new InvalidArgumentException(sprintf('An argument cannot be an instance of "%s".', self::class));
            }

            $normalized[] = PurgeOn::normalizeValue($value);
        }

        $this->values = $normalized;
    }

    /**
     * @return list<ValuesInterface>
     */
    public function getValues(): array
    {
        return $this->values;
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'type' => self::class,
            'values' => array_map(
                static fn (ValuesInterface $values): array => $values->toArray(),
                $this->values,
            ),
        ];
    }
}
