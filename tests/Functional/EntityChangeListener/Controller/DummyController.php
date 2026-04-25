<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Functional\EntityChangeListener\Controller;

use Sofascore\PurgatoryBundle\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle\Tests\Functional\EntityChangeListener\Entity\Dummy;
use Sofascore\PurgatoryBundle\Tests\Functional\EntityChangeListener\Entity\DummyParent;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
class DummyController
{
    #[PurgeOn(Dummy::class, target: 'name')]
    #[PurgeOn(DummyParent::class, target: 'dummy', routeParams: ['name' => 'dummy.name'])]
    #[Route('/{name}', 'test_index')]
    public function index(): void
    {
    }

    #[PurgeOn(Dummy::class, target: 'name')]
    #[Route('/foo', 'test_foo', host: 'example.test')]
    public function foo(): void
    {
    }
}
