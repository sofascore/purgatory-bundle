<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Functional\TestApplication\Controller;

use Sofascore\PurgatoryBundle\Attribute\PurgeContext;
use Sofascore\PurgatoryBundle\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle\Tests\Functional\TestApplication\Entity\Ship;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/ship')]
#[PurgeContext(['varnish' => ['tags' => ['ship'], 'ttl' => 60], 'throttle' => 0])]
class ShipController
{
    #[Route('/{id}', 'ship_details')]
    #[PurgeOn(Ship::class)]
    public function detailsAction(Ship $ship): void
    {
    }

    #[Route('/{id}/engines', 'ship_engines')]
    #[PurgeContext(['varnish' => ['tags' => ['ship', 'engines'], 'ttl' => 5]])]
    #[PurgeOn(Ship::class, target: 'numberOfEngines')]
    #[PurgeOn(Ship::class, target: 'name', context: ['throttle' => 8])]
    public function enginesAction(Ship $ship): void
    {
    }
}
