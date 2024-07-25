<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Functional\TestApplication\Service;

use Sofascore\PurgatoryBundle\Attribute\AsRouteParamService;
use Sofascore\PurgatoryBundle\Tests\Functional\TestApplication\Entity\Animal;
use Sofascore\PurgatoryBundle\Tests\Functional\TestApplication\Entity\Person;

#[AsRouteParamService('purgatory.animal_rating1')]
class AnimalRatingCalculator
{
    public function __invoke(Animal $animal): int
    {
        return $this->getRating($animal) + 100;
    }

    #[AsRouteParamService('purgatory.animal_rating2')]
    public function getRating(Animal $animal): int
    {
        return ($animal->measurements->height ?? 0) + ($animal->measurements->weight ?? 0) + ($animal->measurements->width ?? 0);
    }

    #[AsRouteParamService('purgatory.animal_rating3')]
    public function getOwnerRating(Person $owner): int
    {
        return array_reduce(
            $owner->pets->toArray(),
            fn (int $sum, Animal $pet): int => $sum + $this->getRating($pet),
            initial: 0,
        );
    }
}
