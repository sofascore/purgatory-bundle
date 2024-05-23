<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle2\Cache\Metadata\ControllerMetadataProvider;
use Sofascore\PurgatoryBundle2\DependencyInjection\CompilerPass\ControllerClassMapPass;
use Sofascore\PurgatoryBundle2\Tests\DependencyInjection\Fixtures\DummyService;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Routing\RouterInterface;

#[CoversClass(ControllerClassMapPass::class)]
final class ControllerClassMapPassTest extends TestCase
{
    public function testPurgeOnCollection(): void
    {
        $container = new ContainerBuilder();

        $container->register(id: DummyService::class, class: DummyService::class)
            ->addTag('controller.service_arguments');

        $container->register(id: 'my.controller', class: DummyService::class)
            ->addTag('controller.service_arguments');

        $definition = $container->register('sofascore.purgatory.controller_metadata_provider', ControllerMetadataProvider::class)
            ->setArguments([
                $this->createMock(RouterInterface::class),
                [],
                [],
            ]);

        $compilerPass = new ControllerClassMapPass();
        $compilerPass->process($container);

        $classMap = $definition->getArgument(1);

        self::assertCount(2, $classMap);
        self::assertArrayHasKey(DummyService::class, $classMap);
        self::assertArrayHasKey('my.controller', $classMap);
        self::assertSame(DummyService::class, $classMap[DummyService::class]);
        self::assertSame(DummyService::class, $classMap['my.controller']);
    }
}
