<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle2\Cache\Metadata\ControllerMetadataProvider;
use Sofascore\PurgatoryBundle2\DependencyInjection\CompilerPass\PurgeOnMethodsCompilerPass;
use Sofascore\PurgatoryBundle2\DependencyInjection\PurgatoryExtension;
use Sofascore\PurgatoryBundle2\Tests\DependencyInjection\Fixtures\DummyService;
use Sofascore\PurgatoryBundle2\Tests\DependencyInjection\Fixtures\DummyServiceTwo;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Routing\RouterInterface;

#[CoversClass(PurgeOnMethodsCompilerPass::class)]
class PurgeOnMethodsCompilerPassTest extends TestCase
{
    public function testPurgeOnCollection(): void
    {
        $container = new ContainerBuilder();

        $extension = new PurgatoryExtension();
        $extension->load([], $container);

        $container->register(DummyService::class)
            ->setAutoconfigured(true)
            ->setTags([
                'purgatory.purge_on' => [
                    ['method' => 'methodWithPurgeOn'],
                    ['method' => 'anotherMethodWithPurgeOn'],
                ],
            ]);
        $container->register(DummyServiceTwo::class)
            ->setAutoconfigured(true)
            ->addTag(
                name: 'purgatory.purge_on',
                attributes: ['method' => 'methodWithPurgeOn'],
            );

        $container->register('sofascore.purgatory.controller_metadata_provider', ControllerMetadataProvider::class)
            ->setAutoconfigured(true)
            ->setPublic(true)
            ->setArguments([
                $this->createMock(RouterInterface::class),
                [],
            ]);

        $compilerPass = new PurgeOnMethodsCompilerPass();
        $compilerPass->process($container);

        self::assertTrue($container->has('sofascore.purgatory.controller_metadata_provider'));

        $definition = $container->getDefinition('sofascore.purgatory.controller_metadata_provider');
        self::assertSame(ControllerMetadataProvider::class, $definition->getClass());
        self::assertInstanceOf(RouterInterface::class, $definition->getArgument(0));

        $classMap = $definition->getArgument(1);
        self::assertCount(2, $classMap);
        self::assertArrayHasKey(DummyService::class, $classMap);
        self::assertArrayHasKey(DummyServiceTwo::class, $classMap);
        self::assertSame(['methodWithPurgeOn'], $classMap[DummyServiceTwo::class]);
        self::assertEqualsCanonicalizing(['methodWithPurgeOn', 'anotherMethodWithPurgeOn'], $classMap[DummyService::class]);
    }
}
