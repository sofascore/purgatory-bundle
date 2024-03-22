<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle2\DependencyInjection\PurgatoryExtension;
use Sofascore\PurgatoryBundle2\Tests\DependencyInjection\Fixtures\DummyService;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(PurgatoryExtension::class)]
class PurgatoryExtensionTest extends TestCase
{
    public function testServiceWithPurgeOnIsTagged(): void
    {
        $container = new ContainerBuilder();

        $extension = new PurgatoryExtension();
        $extension->load([], $container);

        $container->register(DummyService::class)
            ->setAutoconfigured(true)
            ->setPublic(true);

        $container->compile();

        self::assertTrue($container->has(DummyService::class));
        self::assertTrue($container->getDefinition(DummyService::class)->hasTag('purgatory.purge_on'));

        $attributes = $container->getDefinition(DummyService::class)->getTag('purgatory.purge_on');

        self::assertCount(2, $attributes);
        self::assertEqualsCanonicalizing(
            ['methodWithPurgeOn', 'anotherMethodWithPurgeOn'],
            array_column($attributes, 'method'),
        );
    }
}
