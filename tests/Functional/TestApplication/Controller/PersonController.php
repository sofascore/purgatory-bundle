<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Functional\TestApplication\Controller;

use Sofascore\PurgatoryBundle\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\CompoundValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\RawValues;
use Sofascore\PurgatoryBundle\Listener\Enum\Action;
use Sofascore\PurgatoryBundle\Tests\Functional\TestApplication\Entity\Animal;
use Sofascore\PurgatoryBundle\Tests\Functional\TestApplication\Entity\Person;
use Sofascore\PurgatoryBundle\Tests\Functional\TestApplication\Enum\Country;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/person')]
class PersonController
{
    #[Route('/{id}', 'person_details')]
    #[PurgeOn(Person::class)]
    public function detailsAction(): void
    {
    }

    #[Route('/list/men', 'person_list_men')]
    #[PurgeOn(Person::class,
        if: new Expression('obj.gender === "male"'),
    )]
    public function personListMaleAction(): void
    {
    }

    #[Route('/list/custom-elf', 'person_list_custom_elf')]
    #[PurgeOn(Person::class,
        if: new Expression('custom_elf(obj)'),
    )]
    public function personListCustomElfAction(): void
    {
    }

    #[Route('/{person}/pets', 'pets_list')]
    #[PurgeOn(Person::class,
        target: 'pets',
        routeParams: [
            'person' => 'id',
        ],
    )]
    public function petsAction(Person $person): void
    {
    }

    #[Route('/{person}/pets2', 'pets_list_alt')]
    #[PurgeOn(Animal::class,
        routeParams: [
            'person' => 'owner.id',
        ],
    )]
    public function petsActionAlternative(Person $person): void
    {
    }

    #[Route('/{person}/pets/names', 'pets_names')]
    #[PurgeOn(Animal::class,
        target: ['name'],
        routeParams: [
            'person' => 'owner.id',
        ],
    )]
    public function petsNamesAction(Person $person): void
    {
    }

    #[Route('/{person}/pets/page/{page}', 'pets_paginated')]
    #[PurgeOn(Animal::class,
        routeParams: [
            'person' => 'owner.id',
            'page' => new RawValues(0, 1),
        ],
    )]
    public function petsPaginatedAction(): void
    {
    }

    #[Route('/deleted', 'deleted_persons')]
    #[PurgeOn(Person::class, actions: Action::Delete)]
    public function deletedPersonsAction(): void
    {
    }

    #[Route('/all-ids', 'all_ids')]
    #[PurgeOn(Person::class, actions: [Action::Create, Action::Delete])]
    public function allIdsAction(): void
    {
    }

    #[Route('/country/{country}', 'person_list_for_country')]
    #[PurgeOn(Person::class,
        target: 'country',
        routeParams: [
            'country' => new CompoundValues('alpha2', new RawValues(null)),
        ],
    )]
    public function personListForCountryAction(?Country $country = null): void
    {
    }

    #[Route('/{id}/cars', 'person_cars_list')]
    #[PurgeOn(Person::class, target: 'cars')]
    #[PurgeOn(Person::class,
        target: 'cars',
        if: "obj.firstName === 'John'",
    )]
    public function personCarsList(Person $person): void
    {
    }

    #[Route('/by-name/{name}', 'list_by_name')]
    #[PurgeOn(Person::class,
        routeParams: [
            'name' => 'firstName',
        ],
    )]
    public function listByNameAction(string $name): void
    {
    }

    #[Route('/by-full-name/{firstName}/{lastName}', 'list_by_full_name')]
    #[PurgeOn(Person::class)]
    public function listByFullNameAction(string $firstName, string $lastName): void
    {
    }

    #[Route('/full-name/{firstName}/{lastName}/gender/{gender}', 'list_by_full_name_and_gender')]
    #[PurgeOn(Person::class)]
    public function listByFullNameAndGenderAction(string $firstName, string $lastName, string $gender): void
    {
    }
}
