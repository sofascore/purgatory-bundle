<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\RouteProvider\PropertyAccess;

use Sofascore\PurgatoryBundle\Exception\ValueNotIterableException;
use Symfony\Component\PropertyAccess\Exception\AccessException;
use Symfony\Component\PropertyAccess\Exception\InvalidArgumentException;
use Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyAccess\PropertyPath;
use Symfony\Component\PropertyAccess\PropertyPathInterface;

/**
 * @internal
 */
final class PurgatoryPropertyAccessor implements PropertyAccessorInterface
{
    private const DELIMITER = '[*].';

    public function __construct(
        private readonly PropertyAccessorInterface $propertyAccessor,
    ) {
    }

    /**
     * @param object|array<array-key, mixed> $objectOrArray
     *
     * @throws InvalidArgumentException
     * @throws AccessException
     * @throws UnexpectedTypeException
     * @throws ValueNotIterableException
     */
    public function getValue(object|array $objectOrArray, string|PropertyPathInterface $propertyPath): mixed
    {
        if (!str_contains((string) $propertyPath, self::DELIMITER)) {
            return $this->propertyAccessor->getValue($objectOrArray, $propertyPath);
        }

        /** @var array{0: string, 1: string} $propertyPathParts */
        $propertyPathParts = explode(separator: self::DELIMITER, string: (string) $propertyPath, limit: 2);

        $basePropertyPath = new PropertyPath($propertyPathParts[0]);
        $collection = $this->propertyAccessor->getValue($objectOrArray, $basePropertyPath);

        if (!is_iterable($collection)) {
            // Honor the null-safe operator: when the collection segment resolves to null because of
            // a null-safe short-circuit (e.g. "property?.collection[*].id" with a null "property"),
            // yield no values instead of throwing. A null collection not guarded by "?."
            // (e.g. "property.collection[*].id" where "collection" itself is null) is still an error.
            if (null === $collection && $this->isNullSafeCollection($objectOrArray, $basePropertyPath)) {
                return null;
            }

            throw new ValueNotIterableException($collection, (string) $basePropertyPath);
        }

        $values = [];

        /** @var object|array<array-key, mixed> $item */
        foreach ($collection as $item) {
            /** @var scalar|list<?scalar>|null $value */
            $value = $this->getValue(
                objectOrArray: $item,
                propertyPath: $propertyPathParts[1],
            );

            $values[] = \is_array($value) ? $value : [$value];
        }

        return array_merge(...$values);
    }

    /**
     * @param object|array<array-key, mixed> $objectOrArray
     *
     * @param-out object|array<array-key, mixed> $objectOrArray
     */
    public function setValue(object|array &$objectOrArray, string|PropertyPathInterface $propertyPath, mixed $value): void
    {
        $this->propertyAccessor->setValue($objectOrArray, $propertyPath, $value);
    }

    /**
     * @param object|array<array-key, mixed> $objectOrArray
     */
    public function isWritable(object|array $objectOrArray, string|PropertyPathInterface $propertyPath): bool
    {
        return $this->propertyAccessor->isWritable($objectOrArray, $propertyPath);
    }

    /**
     * @param object|array<array-key, mixed> $objectOrArray
     */
    public function isReadable(object|array $objectOrArray, string|PropertyPathInterface $propertyPath): bool
    {
        if (!str_contains((string) $propertyPath, self::DELIMITER)) {
            return $this->propertyAccessor->isReadable($objectOrArray, $propertyPath);
        }

        try {
            $this->getValue($objectOrArray, $propertyPath);

            return true;
        } catch (AccessException|UnexpectedTypeException|ValueNotIterableException) {
            return false;
        }
    }

    /**
     * Determines whether a null collection segment is the legitimate result of a null-safe ("?.")
     * short-circuit, rather than the collection itself resolving to null.
     *
     * @param object|array<array-key, mixed> $objectOrArray
     */
    private function isNullSafeCollection(object|array $objectOrArray, PropertyPath $path): bool
    {
        if ($path->isNullSafe($path->getLength() - 1)) {
            return true;
        }

        $parent = $path->getParent();

        return null !== $parent && null === $this->propertyAccessor->getValue($objectOrArray, $parent);
    }
}
