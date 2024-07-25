<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Functional\TestApplication\Controller;

use Sofascore\PurgatoryBundle\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\CompoundValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\DynamicValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\EnumValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\RawValues;
use Sofascore\PurgatoryBundle\Attribute\Target\ForGroups;
use Sofascore\PurgatoryBundle\Attribute\Target\ForProperties;
use Sofascore\PurgatoryBundle\Tests\Functional\TestApplication\Entity\Animal;
use Sofascore\PurgatoryBundle\Tests\Functional\TestApplication\Entity\Person;
use Sofascore\PurgatoryBundle\Tests\Functional\TestApplication\Enum\Country;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route as AnnotationRoute;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/animal')]
#[AnnotationRoute('/animal')]
class AnimalController
{
    #[Route('/{animal_id}', 'animal_details')]
    #[AnnotationRoute('/{animal_id}', name: 'animal_details')]
    #[PurgeOn(Animal::class,
        target: new ForGroups('common'),
        routeParams: [
            'animal_id' => 'id',
        ],
    )]
    public function detailAction(Animal $animal)
    {
    }

    #[Route('/{animal_id}/measurements', 'animal_measurements')]
    #[AnnotationRoute('/{animal_id}/measurements', name: 'animal_measurements')]
    #[PurgeOn(Animal::class,
        target: new ForProperties(['measurements.height', 'measurements.weight']),
        routeParams: [
            'animal_id' => 'id',
        ],
    )]
    public function measurementsAction(Animal $animal)
    {
    }

    #[Route('/{animal_id}/measurements-alt', 'animal_measurements_alt')]
    #[AnnotationRoute('/{animal_id}/measurements-alt', name: 'animal_measurements_alt')]
    #[PurgeOn(Animal::class,
        target: new ForProperties(['goodBoy']),
        routeParams: [
            'animal_id' => 'id',
        ],
    )]
    public function measurementsAltAction(Animal $animal)
    {
    }

    #[Route('/{id}/route1', 'animal_route_1')]
    #[AnnotationRoute('/{id}/route1', name: 'animal_route_1')]
    #[Route('/{id}/route2', 'animal_route_2')]
    #[AnnotationRoute('/{id}/route2', name: 'animal_route_2')]
    #[PurgeOn(Animal::class,
        target: 'measurements.height',
    )]
    #[PurgeOn(Animal::class,
        target: 'measurements.weight',
        route: 'animal_route_1',
    )]
    #[PurgeOn(Animal::class,
        target: 'measurements.width',
        route: 'animal_route_2',
    )]
    public function someRandomRouteAction(Animal $animal)
    {
    }

    #[Route('/pet-of-the-day/{country}', 'pet_of_the_day')]
    #[AnnotationRoute('/pet-of-the-day/{country}', name: 'pet_of_the_day')]
    #[PurgeOn(Animal::class,
        target: new ForGroups('common'),
        routeParams: [
            'country' => new EnumValues(Country::class),
        ],
    )]
    public function petOfTheDayAction(Country $country)
    {
    }

    #[Route('/pet-of-the-month/{country}', 'pet_of_the_month')]
    #[AnnotationRoute('/pet-of-the-month/{country}', name: 'pet_of_the_month')]
    #[PurgeOn(Animal::class,
        target: new ForGroups('common'),
        routeParams: [
            'country' => new CompoundValues(
                new EnumValues(Country::class),
                new RawValues('ar'),
            ),
        ],
    )]
    public function petOfTheMonthAction(string $country)
    {
    }

    #[Route('/for-rating/{rating}', 'animals_with_rating')]
    #[AnnotationRoute('/for-rating/{rating}', name: 'animals_with_rating')]
    #[PurgeOn(Animal::class,
        target: ['measurements'],
        routeParams: [
            'rating' => new CompoundValues(
                new DynamicValues(alias: 'purgatory.animal_rating2'),
                new DynamicValues(alias: 'purgatory.animal_rating1'),
                new DynamicValues(alias: 'purgatory.animal_rating3', arg: 'owner'),
            ),
        ],
    )]
    #[PurgeOn(Person::class,
        target: ['pets'],
        routeParams: [
            'rating' => new CompoundValues(
                new DynamicValues(alias: 'purgatory.animal_rating3'),
            ),
        ],
    )]
    public function animalsForRatingAction(int $rating)
    {
    }

    #[Route('/{id}/owner-details', 'pet_owner_details')]
    #[AnnotationRoute('/{id}/owner-details', name: 'pet_owner_details')]
    #[PurgeOn(Person::class,
        routeParams: [
            'id' => 'pets[*].id',
        ],
    )]
    public function petOwnerDetails(Animal $animal): void
    {
    }

    #[Route('/{id}/owner-details-alt', 'pet_owner_details_alternative')]
    #[AnnotationRoute('/{id}/owner-details-alt', name: 'pet_owner_details_alternative')]
    #[PurgeOn(Person::class,
        routeParams: [
            'id' => 'petsIds',
        ],
    )]
    public function petOwnerDetailsAlternative(Animal $animal): void
    {
    }

    #[Route('/good-boy-ranking', 'good_boy_ranking')]
    #[AnnotationRoute('/good-boy-ranking', name: 'good_boy_ranking')]
    #[PurgeOn(Animal::class,
        target: new ForProperties(['isGoodBoy']),
    )]
    public function goodBoyRankingAction()
    {
    }
}
