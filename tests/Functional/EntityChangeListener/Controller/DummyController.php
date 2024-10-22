<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Functional\EntityChangeListener\Controller;

use Sofascore\PurgatoryBundle\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle\Tests\Functional\EntityChangeListener\Entity\Dummy;
use Sofascore\PurgatoryBundle\Tests\Functional\EntityChangeListener\Entity\DummyParent;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route as AnnotationRoute;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
class DummyController
{
    #[PurgeOn(Dummy::class, target: 'name')]
    #[PurgeOn(DummyParent::class, target: 'dummy', routeParams: ['name' => 'dummy.name'])]
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
