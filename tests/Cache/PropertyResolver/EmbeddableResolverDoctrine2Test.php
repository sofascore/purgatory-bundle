<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Cache\PropertyResolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresMethod;
use Sofascore\PurgatoryBundle2\Cache\PropertyResolver\EmbeddableResolver;

#[CoversClass(EmbeddableResolver::class)]
#[RequiresMethod(ClassMetadataInfo::class, '__construct')]
final class EmbeddableResolverDoctrine2Test extends EmbeddableResolverDoctrine3Test
{
    protected function provideEmbeddedClasses(array $embeddedClasses): array
    {
        return $embeddedClasses;
    }
}
