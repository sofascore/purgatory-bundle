<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Functional\Php85TestApplication\Controller;

use Sofascore\PurgatoryBundle\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\DynamicValues;
use Sofascore\PurgatoryBundle\Listener\Enum\Action;
use Sofascore\PurgatoryBundle\Tests\Functional\Php85TestApplication\Entity\Plant;
use Sofascore\PurgatoryBundle\Tests\Functional\Php85TestApplication\Service\WateringSchedule;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
class WateringController
{
    #[Route('/plants/level/{level}', 'plants_by_level')]
    #[PurgeOn(Plant::class,
        routeParams: [
            'level' => new DynamicValues(static function (Plant $plant): array {
                return [$plant->getWaterLevel() * 10];
            }),
        ],
        actions: Action::Create,
    )]
    public function plantsByLevelAction(): void
    {
    }

    #[Route('/plants/target-level/{level}', 'plants_by_target_level')]
    #[PurgeOn(Plant::class,
        routeParams: [
            'level' => new DynamicValues(static function (Plant $plant, WateringSchedule $schedule, #[Autowire('%kernel.environment%')] string $environment): array {
                return [$environment.'-'.$schedule->getTargetLevel($plant)];
            }),
        ],
        actions: Action::Create,
    )]
    public function plantsByTargetLevelAction(): void
    {
    }

    #[Route('/plants/needing-water', 'plants_needing_water')]
    #[PurgeOn(Plant::class,
        if: static function (Plant $plant, WateringSchedule $schedule): bool {
            return $schedule->needsWater($plant);
        },
        actions: Action::Create,
    )]
    public function plantsNeedingWaterAction(): void
    {
    }
}
