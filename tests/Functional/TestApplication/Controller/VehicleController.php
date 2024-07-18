<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Functional\TestApplication\Controller;

use Sofascore\PurgatoryBundle2\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle2\Tests\Functional\TestApplication\Entity\Vehicle;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route as AnnotationRoute;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/vehicle')]
#[AnnotationRoute('/vehicle')]
class VehicleController
{
    #[Route('/{id}/number-of-engines', 'number_of_engines')]
    #[AnnotationRoute('/{id}/number-of-engines', name: 'number_of_engines')]
    #[PurgeOn(Vehicle::class, target: 'numberOfEngines')]
    public function numberOfEnginesAction(Vehicle $animal)
    {
    }
}
