<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Functional\TestApplication\Controller;

use Sofascore\PurgatoryBundle\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle\Tests\Functional\TestApplication\Entity\Competition\Competition;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/competition')]
class CompetitionController
{
    #[Route('/ordered-by-number-of-pets', 'competitions_ordered_by_number_of_pets')]
    #[PurgeOn(Competition::class, target: 'numberOfPets')]
    public function orderedCompetitionsAction(): void
    {
    }

    #[Route('/by-winner/{winner_id}', 'competitions_by_winner')]
    #[PurgeOn(Competition::class,
        target: 'winner',
        routeParams: [
            'winner_id' => 'winner.id',
        ],
    )]
    public function competitionsByWinnerAction(): void
    {
    }
}
