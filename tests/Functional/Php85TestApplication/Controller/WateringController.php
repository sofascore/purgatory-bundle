<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Functional\Php85TestApplication\Controller;

use Sofascore\PurgatoryBundle\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\DynamicValues;
use Sofascore\PurgatoryBundle\Listener\Enum\Action;
use Sofascore\PurgatoryBundle\Tests\Functional\Php85TestApplication\Entity\Plant;
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
}
