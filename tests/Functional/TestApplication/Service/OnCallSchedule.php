<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Functional\TestApplication\Service;

use Sofascore\PurgatoryBundle\Tests\Functional\TestApplication\Entity\Person;

class OnCallSchedule
{
    public function isOnCall(Person $person): bool
    {
        return 'Beard' === $person->lastName;
    }
}
