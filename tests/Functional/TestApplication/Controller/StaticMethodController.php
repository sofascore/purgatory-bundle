<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Functional\TestApplication\Controller;

use Sofascore\PurgatoryBundle\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle\Tests\Functional\TestApplication\Entity\Person;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/static-method')]
class StaticMethodController
{
    #[Route('/veterinarians', 'static_method_veterinarians')]
    #[PurgeOn(Person::class, if: [self::class, 'isVeterinarian'])]
    public function veterinariansAction(): void
    {
    }

    #[Route('/veterinarian/{id}/patients', 'static_method_veterinarian_patients')]
    #[PurgeOn(Person::class,
        target: 'animalPatients',
        routeParams: [
            'id' => 'id',
        ],
        if: [self::class, 'isVeterinarian'],
    )]
    public function veterinarianPatientsAction(): void
    {
    }

    public static function isVeterinarian(Person $person): bool
    {
        return $person->isVeterinarian;
    }
}
