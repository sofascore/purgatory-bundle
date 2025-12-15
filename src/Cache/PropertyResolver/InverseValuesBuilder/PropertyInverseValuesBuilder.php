<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Cache\PropertyResolver\InverseValuesBuilder;

use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\PropertyValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\ValuesInterface;

/**
 * @implements InverseValuesBuilderInterface<PropertyValues>
 */
final class PropertyInverseValuesBuilder implements InverseValuesBuilderInterface
{
    public static function for(): string
    {
        return PropertyValues::type();
    }

    public function build(ValuesInterface $values, string $associationClass, string $associationTarget): ValuesInterface
    {
        return new PropertyValues(...array_map(
            static fn (string $property): string => \sprintf('%s?.%s', $associationTarget, $property),
            $values->getValues(),
        ));
    }
}
