<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Functional\EntityChangeListener\Controller;

use Sofascore\PurgatoryBundle2\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle2\Attribute\Target\ForProperties;
use Sofascore\PurgatoryBundle2\Tests\Functional\EntityChangeListener\Entity\Dummy;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route as AnnotationRoute;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
class DummyController
{
    #[PurgeOn(Dummy::class, new ForProperties('name'))]
    #[AnnotationRoute('/{name}', name: 'test_index')]
    #[Route('/{name}', 'test_index')]
    public function index()
    {
    }
}
