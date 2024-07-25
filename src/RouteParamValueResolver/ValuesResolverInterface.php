<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\RouteParamValueResolver;

/**
 * @template T of array
 */
interface ValuesResolverInterface
{
    public static function for(): string;

    /**
     * @param T $unresolvedValues
     *
     * @return list<?scalar>
     */
    public function resolve(array $unresolvedValues, object $entity): array;
}
