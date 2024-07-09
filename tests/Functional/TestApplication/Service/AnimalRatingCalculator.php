<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Functional\TestApplication\Service;

use Sofascore\PurgatoryBundle2\Attribute\AsPurgatoryParamResolver;
use Sofascore\PurgatoryBundle2\Tests\Functional\TestApplication\Entity\Animal;
use Sofascore\PurgatoryBundle2\Tests\Functional\TestApplication\Entity\Person;

#[AsPurgatoryParamResolver('purgatory2.animal_rating')]
class AnimalRatingCalculator
{
    public function __invoke(Animal $animal): int
    {
        return $this->getRating($animal) + 100;
    }

    public function getRating(Animal $animal): int
    {
        return ($animal->measurements->height ?? 0) + ($animal->measurements->weight ?? 0) + ($animal->measurements->width ?? 0);
    }

    public function getOwnerRating(Person $owner): int
    {
        return array_reduce(
            $owner->pets->toArray(),
            fn (int $sum, Animal $pet): int => $sum + $this->getRating($pet),
            initial: 0,
        );
    }
}
