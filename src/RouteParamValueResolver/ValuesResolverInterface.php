<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\RouteParamValueResolver;

use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\ValuesInterface;

/**
 * @template T
 */
interface ValuesResolverInterface
{
    /**
     * @return class-string<ValuesInterface>
     */
    public static function for(): string;

    /**
     * @param list<T> $unresolvedValues
     *
     * @return list<?scalar>
     */
    public function resolve(array $unresolvedValues, object $entity): array;
}
