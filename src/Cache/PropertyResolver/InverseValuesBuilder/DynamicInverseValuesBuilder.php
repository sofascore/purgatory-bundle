<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Cache\PropertyResolver\InverseValuesBuilder;

use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\DynamicValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\ValuesInterface;

/**
 * @implements InverseValuesBuilderInterface<DynamicValues>
 */
final class DynamicInverseValuesBuilder implements InverseValuesBuilderInterface
{
    public static function for(): string
    {
        return DynamicValues::type();
    }

    public function build(ValuesInterface $values, string $associationClass, string $associationTarget): ValuesInterface
    {
        /** @var string $alias */
        [$alias, $arg] = $values->getValues();

        return new DynamicValues(
            alias: $alias,
            arg: null !== $arg ? \sprintf('%s?.%s', $associationTarget, $arg) : $associationTarget,
        );
    }
}
