<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Functional\Php85TestApplication\Controller;

use Sofascore\PurgatoryBundle\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle\Listener\Enum\Action;
use Sofascore\PurgatoryBundle\Tests\Functional\Php85TestApplication\Entity\Garden;
use Sofascore\PurgatoryBundle\Tests\Functional\Php85TestApplication\Entity\Plant;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
class PlantController
{
    #[Route('/plants/dry', 'dry_plants_list')]
    #[PurgeOn(Plant::class,
        if: static function (Plant $plant): bool {
            return 0 === $plant->getWaterLevel();
        },
        actions: Action::Create,
    )]
    public function dryPlantsAction(): void
    {
    }

    #[Route('/gardens/{garden}/plants', 'garden_plants_list')]
    #[PurgeOn(Garden::class,
        target: 'plants',
        routeParams: [
            'garden' => 'id',
        ],
        if: static function (Garden $garden): bool {
            return $garden->isPublic();
        },
    )]
    #[PurgeOn(Garden::class,
        target: 'bestPlant',
        routeParams: [
            'garden' => 'id',
        ],
        if: static function (Garden $garden): bool {
            return $garden->isPublic();
        },
    )]
    public function gardenPlantsAction(): void
    {
    }

    #[Route('/plants/thirsty', 'thirsty_plants_list')]
    #[PurgeOn(Plant::class, if: static function (Plant $plant): bool { return \in_array($plant->getWaterLevel(), [1, 2], true); /* 1, 2) */ }, actions: [Action::Create, Action::Update])]
    public function thirstyPlantsAction(): void
    {
    }

    #[Route('/plants/flooded', 'flooded_plants_list')]
    #[PurgeOn(Plant::class, actions: Action::Update, if: static function (Plant $plant): bool {
        // braces in comments and strings don't end the closure }
        return $plant->getWaterLevel() > 100 && '}\'' !== (string) $plant->getWaterLevel();
    })]
    public function floodedPlantsAction(): void
    {
    }
}
