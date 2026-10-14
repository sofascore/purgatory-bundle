<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Functional\TestApplication\Controller;

use Sofascore\PurgatoryBundle\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\DynamicValues;
use Sofascore\PurgatoryBundle\Listener\Enum\Action;
use Sofascore\PurgatoryBundle\Tests\Functional\TestApplication\Entity\Animal;
use Sofascore\PurgatoryBundle\Tests\Functional\TestApplication\Entity\Person;
use Sofascore\PurgatoryBundle\Tests\Functional\TestApplication\Service\AnimalRatingCalculator;
use Sofascore\PurgatoryBundle\Tests\Functional\TestApplication\Service\OnCallSchedule;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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

    #[Route('/on-call-veterinarians', 'static_method_on_call_veterinarians')]
    #[PurgeOn(Person::class, if: [self::class, 'isOnCallVeterinarian'])]
    public function onCallVeterinariansAction(): void
    {
    }

    #[Route('/animals/{rating}', 'static_method_animals_by_rating')]
    #[PurgeOn(Animal::class,
        routeParams: [
            'rating' => new DynamicValues([self::class, 'getRating']),
        ],
        actions: Action::Create,
    )]
    public function animalsByRatingAction(): void
    {
    }

    public static function isVeterinarian(Person $person): bool
    {
        return $person->isVeterinarian;
    }

    public static function isOnCallVeterinarian(Person $person, OnCallSchedule $schedule): bool
    {
        return $person->isVeterinarian && $schedule->isOnCall($person);
    }

    public static function getRating(
        Animal $animal,
        AnimalRatingCalculator $calculator,
        #[Autowire('%kernel.environment%')] string $environment,
    ): string {
        return $environment.'-'.$calculator->getRating($animal);
    }
}
