<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Functional\VarnishPurger\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route as AnnotationRoute;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
class DummyController
{
    #[AnnotationRoute('/', name: 'test_index')]
    #[Route('/', 'test_index')]
    public function index()
    {
        return (new Response((string) microtime(true)))->setCache([
            'max_age' => 3600,
            'public' => true,
        ]);
    }
}
