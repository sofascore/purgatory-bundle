<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Functional\DebugCommand\Controller;

use Sofascore\PurgatoryBundle\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle\Tests\Functional\DebugCommand\Entity\Author;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/author')]
class AuthorController
{
    #[Route('/{author_id}', 'author_show')]
    #[PurgeOn(Author::class,
        routeParams: [
            'author_id' => 'id',
        ],
    )]
    public function show(Author $author): void
    {
    }
}
