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
        return new DynamicValues(
            provider: $values->provider,
            propertyPath: null !== $values->propertyPath ? \sprintf('%s?.%s', $associationTarget, $values->propertyPath) : $associationTarget,
        );
    }
}
