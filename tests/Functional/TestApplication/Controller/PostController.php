<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Functional\TestApplication\Controller;

use Sofascore\PurgatoryBundle\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle\Attribute\Target\ForResponseGroups;
use Sofascore\PurgatoryBundle\Tests\Functional\TestApplication\Entity\Post;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\Serialize;
use Symfony\Component\Routing\Annotation\Route as AnnotationRoute;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Only registered on Symfony 8.1 and higher, see TestKernel.
 */
#[AsController]
#[Route('/post')]
#[AnnotationRoute('/post')]
class PostController
{
    #[Route('/{post_id}', 'post_details')]
    #[AnnotationRoute('/{post_id}', name: 'post_details')]
    #[PurgeOn(Post::class,
        target: new ForResponseGroups(),
        routeParams: [
            'post_id' => 'id',
        ],
    )]
    #[Serialize(context: ['groups' => 'common'])]
    public function detailsAction(Post $post): Post
    {
        return $post;
    }

    #[Route('/{post_id}/full', 'post_full_details')]
    #[AnnotationRoute('/{post_id}/full', name: 'post_full_details')]
    #[PurgeOn(Post::class,
        target: new ForResponseGroups(),
        routeParams: [
            'post_id' => 'id',
        ],
    )]
    #[Serialize(context: ['groups' => ['common', 'extra']])]
    public function fullDetailsAction(Post $post): Post
    {
        return $post;
    }
}
