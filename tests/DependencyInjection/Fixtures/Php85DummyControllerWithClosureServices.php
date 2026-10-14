<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\DependencyInjection\Fixtures;

use Sofascore\PurgatoryBundle\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\DynamicValues;

/**
 * Closures in attributes only parse on PHP 8.5+, so this class is referenced only by PHP 8.5+ tests.
 */
class Php85DummyControllerWithClosureServices
{
    #[PurgeOn(\stdClass::class,
        routeParams: [
            'foo' => new DynamicValues(static function (object $entity, \Countable $countable): array {
                return [];
            }),
        ],
        if: static function (\stdClass $entity, DummyRouteParamService $service): bool {
            return true;
        },
    )]
    public function __invoke(): void
    {
    }
}
