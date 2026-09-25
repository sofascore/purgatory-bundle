<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\RouteParamValueResolver\Fixtures;

use Sofascore\PurgatoryBundle\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\DynamicValues;

/**
 * Closures in attributes only parse on PHP 8.5+, so this file is loaded only by PHP 8.5+ tests.
 */
final class CountingClosureHolder
{
    #[PurgeOn(\stdClass::class, routeParams: ['foo' => new DynamicValues(static function (object $entity): array {
        static $calls = 0;

        return [++$calls];
    })])]
    public function action(): void
    {
    }
}
