<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Functional\EntityChangeListener\Controller;

use Sofascore\PurgatoryBundle2\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle2\Tests\Functional\EntityChangeListener\Entity\Dummy;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route as AnnotationRoute;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
class DummyController
{
    #[PurgeOn(Dummy::class, target: 'name')]
    #[AnnotationRoute('/{name}', name: 'test_index')]
    #[Route('/{name}', 'test_index')]
    public function index()
    {
    }

    #[PurgeOn(Dummy::class, target: 'name')]
    #[AnnotationRoute('/foo', name: 'test_foo', host: 'example.test')]
    #[Route('/foo', 'test_foo', host: 'example.test')]
    public function foo()
    {
    }
}
