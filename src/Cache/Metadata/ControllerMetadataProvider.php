<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Cache\Metadata;

use Symfony\Component\Routing\RouterInterface;

class ControllerMetadataProvider implements ControllerMetadataProviderInterface
{
    public function __construct(
        private readonly RouterInterface $router,
        private readonly array $classMap,
    ) {
    }

    public function provide(): iterable
    {
        // TODO in next PR
        yield new ControllerMetadata();
    }
}
