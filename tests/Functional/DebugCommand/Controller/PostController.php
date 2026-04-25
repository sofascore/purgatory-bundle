<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Functional\DebugCommand\Controller;

use Sofascore\PurgatoryBundle\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\CompoundValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\DynamicValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\EnumValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\ExpressionValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\RawValues;
use Sofascore\PurgatoryBundle\Attribute\Target\ForGroups;
use Sofascore\PurgatoryBundle\Listener\Enum\Action;
use Sofascore\PurgatoryBundle\Tests\Functional\DebugCommand\Entity\Author;
use Sofascore\PurgatoryBundle\Tests\Functional\DebugCommand\Entity\Post;
use Sofascore\PurgatoryBundle\Tests\Functional\DebugCommand\Entity\Tag;
use Sofascore\PurgatoryBundle\Tests\Functional\DebugCommand\Enum\LanguageCodes;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/post')]
class PostController
{
    #[Route('/{post_id}', 'post_show')]
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
        actions: [Action::Update, Action::Delete],
    )]
    public function show(Post $post)
    {
    }

    #[Route('/{lang}/{page}', 'post_list')]
    #[PurgeOn(Post::class,
        target: new ForGroups('common'),
        routeParams: [
            'lang' => new CompoundValues(
                new EnumValues(LanguageCodes::class),
                new RawValues('XK'), // Kosovo
            ),
            'page' => new DynamicValues('purgatory.get_page'),
        ],
    )]
    public function list()
    {
    }

    #[Route('/{author_id}/{tag_id}', 'post_filter')]
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

    #[Route('/author-full-name/{full_name}', 'post_filter_by_author_full_name')]
    #[PurgeOn(Author::class,
        target: ['firstName', 'lastName'],
        routeParams: [
            'full_name' => new ExpressionValues('obj.firstName~"-"~obj.lastName'),
        ],
        if: static function (Author $author): bool {
            return !$author->getPosts()->isEmpty()
                && null !== $author->getFirstName()
                && null !== $author->getLastName();
        },
    )]
    public function filterByAuthorFullName()
    {
    }
}
