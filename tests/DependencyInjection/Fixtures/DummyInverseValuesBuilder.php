<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\DependencyInjection\Fixtures;

use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\ValuesInterface;
use Sofascore\PurgatoryBundle\Cache\PropertyResolver\InverseValuesBuilder\InverseValuesBuilderInterface;

final class DummyInverseValuesBuilder implements InverseValuesBuilderInterface
{
    /**
     * {@inheritDoc}
     */
    public static function for(): string
    {
        return '';
    }

    /**
     * {@inheritDoc}
     */
    public function build(ValuesInterface $values, string $associationClass, string $associationTarget): ValuesInterface
    {
        return $values;
    }
}
