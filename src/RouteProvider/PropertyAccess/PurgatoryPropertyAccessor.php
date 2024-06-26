<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\RouteProvider\PropertyAccess;

use Sofascore\PurgatoryBundle2\Exception\ValueNotIterableException;
use Symfony\Component\PropertyAccess\Exception\AccessException;
use Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
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
     * @param string|PropertyPathInterface   $propertyPath
     */
    public function getValue($objectOrArray, $propertyPath): mixed
    {
        if (!str_contains((string) $propertyPath, self::DELIMITER)) {
            return $this->propertyAccessor->getValue($objectOrArray, $propertyPath);
        }

        /** @var array{0: string, 1: string} $propertyPathParts */
        $propertyPathParts = explode(separator: self::DELIMITER, string: (string) $propertyPath, limit: 2);

        $collection = $this->propertyAccessor->getValue($objectOrArray, $propertyPathParts[0]);

        if (!is_iterable($collection)) {
            throw new ValueNotIterableException($collection, $propertyPathParts[0]);
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
     * @param string|PropertyPathInterface   $propertyPath
     */
    public function setValue(&$objectOrArray, $propertyPath, mixed $value): void
    {
        $this->propertyAccessor->setValue($objectOrArray, $propertyPath, $value);
    }

    /**
     * @param object|array<array-key, mixed> $objectOrArray
     * @param string|PropertyPathInterface   $propertyPath
     */
    public function isWritable($objectOrArray, $propertyPath): bool
    {
        return $this->propertyAccessor->isWritable($objectOrArray, $propertyPath);
    }

    /**
     * @param object|array<array-key, mixed> $objectOrArray
     * @param string|PropertyPathInterface   $propertyPath
     */
    public function isReadable($objectOrArray, $propertyPath): bool
    {
        if (!str_contains((string) $propertyPath, self::DELIMITER)) {
            return $this->propertyAccessor->isReadable($objectOrArray, $propertyPath);
        }

        try {
            $this->getValue($objectOrArray, $propertyPath);

            return true;
        } catch (AccessException|UnexpectedTypeException) {
            return false;
        }
    }
}
