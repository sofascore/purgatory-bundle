<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Cache\Metadata;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle2\Cache\Metadata\ControllerMetadataProvider;

#[CoversClass(ControllerMetadataProvider::class)]
class ControllerMetadataProviderTest extends TestCase
{
    #[DoesNotPerformAssertions]
    public function testControllerMetadata(): void
    {
        // TODO in next PR
    }
}
