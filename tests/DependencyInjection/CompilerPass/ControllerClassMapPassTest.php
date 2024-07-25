<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Cache\RouteMetadata\AttributeMetadataProvider;
use Sofascore\PurgatoryBundle\DependencyInjection\CompilerPass\ControllerClassMapPass;
use Sofascore\PurgatoryBundle\Tests\DependencyInjection\Fixtures\DummyController;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Routing\RouterInterface;

#[CoversClass(ControllerClassMapPass::class)]
final class ControllerClassMapPassTest extends TestCase
{
    #[TestWith([DummyController::class, DummyController::class, true])]
    #[TestWith([DummyController::class, DummyController::class, false])]
    #[TestWith(['my.controller', DummyController::class, true])]
    #[TestWith(['my.controller', DummyController::class, false])]
    public function testPurgeOnCollection(string $id, string $class, bool $hasAttribute): void
    {
        $container = new ContainerBuilder();

        $container->register(id: $id, class: $class)->addTag(
            name: 'purgatory.purge_on',
            attributes: $hasAttribute ? ['class' => $class] : [],
        );

        $definition = $container->register('sofascore.purgatory.route_metadata_provider.attribute', AttributeMetadataProvider::class)
            ->setArguments([
                $this->createMock(RouterInterface::class),
                [],
                [],
            ]);

        $compilerPass = new ControllerClassMapPass();
        $compilerPass->process($container);

        $classMap = $definition->getArgument(1);

        self::assertCount(1, $classMap);
        self::assertArrayHasKey($id, $classMap);
        self::assertSame($class, $classMap[$id]);
    }
}
