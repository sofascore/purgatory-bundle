<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Cache\PropertyResolver\InverseValuesBuilder;

use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\ValuesInterface;

/**
 * @template T of ValuesInterface
 */
interface InverseValuesBuilderInterface
{
    public static function for(): string;

    /**
     * @param T $values
     *
     * @return T
     */
    public function build(ValuesInterface $values, string $associationClass, string $associationTarget): ValuesInterface;
}
