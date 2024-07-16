<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\RouteParamValueResolver;

use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\PropertyValues;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * @implements ValuesResolverInterface<non-empty-list<string>>
 */
final class PropertyValuesResolver implements ValuesResolverInterface
{
    public function __construct(
        private readonly PropertyAccessorInterface $propertyAccessor,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public static function for(): string
    {
        return PropertyValues::type();
    }

    /**
     * {@inheritDoc}
     */
    public function resolve(array $unresolvedValues, object $entity): array
    {
        /** @var list<?scalar> $values */
        $values = [];

        foreach ($unresolvedValues as $property) {
            /** @var scalar|list<?scalar>|null $value */
            $value = $this->propertyAccessor->getValue($entity, $property);

            if (\is_array($value)) {
                array_push($values, ...$value);
            } else {
                $values[] = $value;
            }
        }

        return $values;
    }
}
