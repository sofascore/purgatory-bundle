<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\RouteParamValueResolver;

use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\PropertyValues;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * @implements ValuesResolverInterface<string>
 */
final class PropertyValuesResolver implements ValuesResolverInterface
{
    public function __construct(
        protected readonly PropertyAccessorInterface $propertyAccessor,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public static function for(): string
    {
        return PropertyValues::class;
    }

    /**
     * {@inheritDoc}
     */
    public function resolve(array $unresolvedValues, object $entity): array
    {
        /** @var list<?scalar> $values */
        $values = [];

        foreach ($unresolvedValues as $property) {
            /** @var ?scalar $value */
            $value = $this->propertyAccessor->getValue($entity, $property);
            $values[] = $value;
        }

        return $values;
    }
}
