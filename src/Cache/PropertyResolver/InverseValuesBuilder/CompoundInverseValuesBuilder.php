<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Cache\PropertyResolver\InverseValuesBuilder;

use Psr\Container\ContainerInterface;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\CompoundValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\ValuesInterface;

/**
 * @implements InverseValuesBuilderInterface<CompoundValues>
 */
final class CompoundInverseValuesBuilder implements InverseValuesBuilderInterface
{
    public function __construct(
        private readonly ContainerInterface $inverseValuesBuilderLocator,
    ) {
    }

    public static function for(): string
    {
        return CompoundValues::type();
    }

    public function build(ValuesInterface $values, string $associationClass, string $associationTarget): ValuesInterface
    {
        return new CompoundValues(
            ...array_map(
                fn (ValuesInterface $values): ValuesInterface => $this->getInverseValuesBuilderFor($values)
                    ?->build($values, $associationClass, $associationTarget)
                    ?? $values,
                $values->getValues(),
            ),
        );
    }

    /**
     * @template T of ValuesInterface
     *
     * @param T $values
     *
     * @return ?InverseValuesBuilderInterface<T>
     */
    private function getInverseValuesBuilderFor(ValuesInterface $values): ?InverseValuesBuilderInterface
    {
        /** @var ?InverseValuesBuilderInterface<T> $builder */
        $builder = $this->inverseValuesBuilderLocator->has($type = $values::type())
            ? $this->inverseValuesBuilderLocator->get($type)
            : null;

        return $builder;
    }
}
