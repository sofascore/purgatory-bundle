<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\RouteParamValueResolver;

use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\PropertyValues;
use Sofascore\PurgatoryBundle\RouteProvider\PropertyAccess\PurgatoryPropertyAccessor;

/**
 * @implements ValuesResolverInterface<non-empty-list<string>>
 */
final class PropertyValuesResolver implements ValuesResolverInterface
{
    public function __construct(
        private readonly PurgatoryPropertyAccessor $propertyAccessor,
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
