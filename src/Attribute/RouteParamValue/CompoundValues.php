<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Attribute\RouteParamValue;

use Sofascore\PurgatoryBundle\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle\Exception\InvalidArgumentException;

final class CompoundValues extends AbstractValues implements InverseValuesAwareInterface
{
    /**
     * @var non-empty-list<ValuesInterface>
     */
    private readonly array $values;

    /**
     * @param string|non-empty-list<string>|ValuesInterface $value
     * @param string|non-empty-list<string>|ValuesInterface ...$values
     */
    public function __construct(
        string|array|ValuesInterface $value,
        string|array|ValuesInterface ...$values,
    ) {
        $values = [$value, ...$values];

        /** @var non-empty-list<ValuesInterface> $normalized */
        $normalized = [];

        foreach ($values as $value) {
            if ($value instanceof self) {
                throw new InvalidArgumentException(\sprintf('An argument cannot be an instance of "%s".', self::class));
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
            'type' => self::type(),
            'values' => array_map(
                static fn (ValuesInterface $values): array => $values->toArray(),
                $this->values,
            ),
        ];
    }

    public function buildInverseValuesFor(string $association): ValuesInterface
    {
        return new self(
            ...array_map(
                static fn (ValuesInterface $values): ValuesInterface => $values instanceof InverseValuesAwareInterface
                    ? $values->buildInverseValuesFor($association)
                    : $values,
                $this->values,
            ),
        );
    }

    public static function type(): string
    {
        return 'compound';
    }
}
