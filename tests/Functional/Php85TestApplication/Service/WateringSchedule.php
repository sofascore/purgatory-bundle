<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Functional\Php85TestApplication\Service;

use Sofascore\PurgatoryBundle\Tests\Functional\Php85TestApplication\Entity\Plant;

class WateringSchedule
{
    public function needsWater(Plant $plant): bool
    {
        return $plant->getWaterLevel() < 3;
    }

    public function getTargetLevel(Plant $plant): int
    {
        return $plant->getWaterLevel() + 5;
    }
}
