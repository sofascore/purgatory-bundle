<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Functional\TestApplication\Controller;

use Sofascore\PurgatoryBundle2\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle2\Tests\Functional\TestApplication\Entity\Competition\Competition;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route as AnnotationRoute;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/competition')]
#[AnnotationRoute('/competition')]
class CompetitionController
{
    #[Route('/ordered-by-number-of-pets', 'competitions_ordered_by_number_of_pets')]
    #[AnnotationRoute('/ordered-by-number-of-pets', name: 'competitions_ordered_by_number_of_pets')]
    #[PurgeOn(Competition::class, target: 'numberOfPets')]
    public function orderedCompetitionsAction()
    {
    }

    #[Route('/by-winner/{winner_id}', 'competitions_by_winner')]
    #[AnnotationRoute('/by-winner/{winner_id}', name: 'competitions_by_winner')]
    #[PurgeOn(Competition::class,
        target: 'winner',
        routeParams: [
            'winner_id' => 'winner.id',
        ],
    )]
    public function competitionsByWinnerAction()
    {
    }
}
