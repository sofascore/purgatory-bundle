<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\DependencyInjection\Fixtures;

use Sofascore\PurgatoryBundle\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\CompoundValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\DynamicValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\RawValues;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;

#[PurgeOn(\stdClass::class, if: [self::class, 'withoutServices'])]
class DummyControllerWithCallableServices
{
    #[PurgeOn(\stdClass::class,
        routeParams: [
            'foo' => new CompoundValues(new RawValues(1), new DynamicValues([self::class, 'provide'])),
            'bar' => new DynamicValues('some_alias'),
        ],
        if: [self::class, 'withServices'],
    )]
    public function __invoke(): void
    {
    }

    #[PurgeOn(\stdClass::class, if: [self::class, 'withServices'])]
    public function sameCallable(): void
    {
    }

    public static function withoutServices(\stdClass $entity): bool
    {
        return true;
    }

    public static function withServices(
        \stdClass $entity,
        DummyRouteParamService $service,
        ?\ArrayObject $nullable,
        #[Autowire(service: 'some_service')] object $autowired,
        #[Autowire('%kernel.environment%')] string $environment,
        int $scalar = 1,
        ?\SplQueue $optional = null,
    ): bool {
        return true;
    }

    /**
     * @return list<int>
     */
    public static function provide(object $entity, #[Target('someName')] \Countable $countable): array
    {
        return [];
    }

    /**
     * @return list<int>
     */
    public static function provideFromYaml(object $entity, DummyRouteParamService $service): array
    {
        return [];
    }
}
