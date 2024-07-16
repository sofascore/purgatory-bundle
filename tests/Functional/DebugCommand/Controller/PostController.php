<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Functional\DebugCommand\Controller;

use Sofascore\PurgatoryBundle2\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\CompoundValues;
use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\DynamicValues;
use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\EnumValues;
use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\RawValues;
use Sofascore\PurgatoryBundle2\Attribute\Target\ForGroups;
use Sofascore\PurgatoryBundle2\Tests\Functional\DebugCommand\Entity\Author;
use Sofascore\PurgatoryBundle2\Tests\Functional\DebugCommand\Entity\Post;
use Sofascore\PurgatoryBundle2\Tests\Functional\DebugCommand\Entity\Tag;
use Sofascore\PurgatoryBundle2\Tests\Functional\DebugCommand\Enum\LanguageCodes;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route as AnnotationRoute;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/post')]
#[AnnotationRoute('/post')]
class PostController
{
    #[Route('/{post_id}', 'post_show')]
    #[AnnotationRoute('/{post_id}', name: 'post_show')]
    #[PurgeOn(Post::class,
        routeParams: [
            'post_id' => 'id',
        ],
        if: 'obj.isActive() === true',
    )]
    #[PurgeOn(Author::class,
        target: new ForGroups('common'),
        routeParams: [
            'post_id' => 'posts[*].id',
        ],
    )]
    public function show(Post $post)
    {
    }

    #[Route('/{lang}/{page}', 'post_list')]
    #[AnnotationRoute('/{lang}/{page}', name: 'post_list')]
    #[PurgeOn(Post::class,
        target: new ForGroups('common'),
        routeParams: [
            'lang' => new CompoundValues(
                new EnumValues(LanguageCodes::class),
                new RawValues('XK'), // Kosovo
            ),
            'page' => new DynamicValues('purgatory2.get_page'),
        ],
    )]
    public function list()
    {
    }

    #[Route('/{author_id}/{tag_id}', 'post_filter')]
    #[AnnotationRoute('/{author_id}/{tag_id}', name: 'post_filter')]
    #[PurgeOn(Author::class,
        target: new ForGroups('common'),
        routeParams: [
            'author_id' => 'id',
            'tag_id' => 'posts[*].tags[*].id',
        ],
    )]
    #[PurgeOn(Tag::class,
        target: 'name',
        routeParams: [
            'author_id' => 'posts[*].author.id',
            'tag_id' => 'id',
        ],
    )]
    public function filterByAuthorAndTag(Author $author)
    {
    }
}
