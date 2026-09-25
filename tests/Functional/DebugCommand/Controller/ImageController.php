<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Functional\DebugCommand\Controller;

use Sofascore\PurgatoryBundle\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\DynamicValues;
use Sofascore\PurgatoryBundle\Tests\Functional\DebugCommand\Entity\Author;
use Sofascore\PurgatoryBundle\Tests\Functional\DebugCommand\Entity\Image;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/image')]
class ImageController
{
    #[Route('/{name}', 'image_show')]
    #[PurgeOn(Image::class,
        routeParams: [
            'name' => new DynamicValues([self::class, 'getNames']),
        ],
        if: [self::class, 'hasPath'],
    )]
    public function show(): void
    {
    }

    #[Route('/writer/{writer_id}', 'image_list_by_writer')]
    #[PurgeOn(Author::class,
        target: 'posts',
        routeParams: [
            'writer_id' => 'id',
        ],
        if: [self::class, 'hasImage'],
    )]
    public function listByWriter(): void
    {
    }

    /**
     * @return list<?string>
     */
    public static function getNames(Image $image): array
    {
        return [$image->getName()];
    }

    public static function hasPath(Image $image): bool
    {
        return null !== $image->getPath();
    }

    public static function hasImage(Author $author): bool
    {
        return null !== $author->getImage();
    }
}
