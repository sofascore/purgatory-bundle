<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Functional\TestApplication\Controller;

use Sofascore\PurgatoryBundle2\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\RawValues;
use Sofascore\PurgatoryBundle2\Listener\Enum\Action;
use Sofascore\PurgatoryBundle2\Tests\Functional\TestApplication\Entity\Animal;
use Sofascore\PurgatoryBundle2\Tests\Functional\TestApplication\Entity\Person;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route as AnnotationRoute;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/person')]
#[AnnotationRoute('/person')]
class PersonController extends AbstractController
{
    #[Route('/{id}', 'person_details')]
    #[AnnotationRoute('/{id}', name: 'person_details')]
    #[PurgeOn(Person::class)]
    public function detailsAction()
    {
    }

    #[Route('/list/men', 'person_list_men')]
    #[AnnotationRoute('/list/men', name: 'person_list_men')]
    #[PurgeOn(Person::class,
        if: new Expression('obj.gender === "male"'),
    )]
    public function personListMaleAction()
    {
    }

    #[Route('/{person}/pets', 'pets_list')]
    #[AnnotationRoute('/{person}/pets', name: 'pets_list')]
    #[PurgeOn(Person::class,
        target: 'pets',
        routeParams: [
            'person' => 'id',
        ],
    )]
    public function petsAction(Person $person)
    {
    }

    #[Route('/{person}/pets2', 'pets_list_alt')]
    #[AnnotationRoute('/{person}/pets2', name: 'pets_list_alt')]
    #[PurgeOn(Animal::class,
        routeParams: [
            'person' => 'owner.id',
        ],
    )]
    public function petsActionAlternative(Person $person)
    {
    }

    #[Route('/{person}/pets/names', 'pets_names')]
    #[AnnotationRoute('/{person}/pets/names', name: 'pets_names')]
    #[PurgeOn(Animal::class,
        target: ['name'],
        routeParams: [
            'person' => 'owner.id',
        ],
    )]
    public function petsNamesAction(Person $person)
    {
    }

    #[Route('/{person}/pets/page/{page}', 'pets_paginated')]
    #[AnnotationRoute('/{person}/pets/page/{page}', name: 'pets_paginated')]
    #[PurgeOn(Animal::class,
        routeParams: [
            'person' => 'owner.id',
            'page' => new RawValues(0, 1),
        ],
    )]
    public function petsPaginatedAction()
    {
    }

    #[Route('/deleted', 'deleted_persons')]
    #[AnnotationRoute('/deleted', name: 'deleted_persons')]
    #[PurgeOn(Person::class, actions: Action::Delete)]
    public function deletedPersonsAction()
    {
    }

    #[Route('/all-ids', 'all_ids')]
    #[AnnotationRoute('/all-ids', name: 'all_ids')]
    #[PurgeOn(Person::class, actions: [Action::Create, Action::Delete])]
    public function allIdsAction()
    {
    }
}
