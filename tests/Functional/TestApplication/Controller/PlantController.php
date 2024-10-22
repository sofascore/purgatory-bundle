<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Functional\TestApplication\Controller;

use Sofascore\PurgatoryBundle\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle\Tests\Functional\TestApplication\Entity\Plant;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route as AnnotationRoute;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/plant')]
#[AnnotationRoute('/plant')]
class PlantController
{
    #[Route('/{id}', 'plant_details')]
    #[AnnotationRoute('/{id}', name: 'plant_details')]
    #[PurgeOn(Plant::class, context: ['qux' => true, 'corge' => 2])]
    public function detailsAction()
    {
    }
}
