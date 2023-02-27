<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\DependencyInjection\CompilerPass;

use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\DependencyInjection\CompilerPass\RegisterPurgerImplementationCompilerPass;
use Sofascore\PurgatoryBundle\Mapping\Loader\AnnotationsLoader;
use Sofascore\PurgatoryBundle\Purger\PurgerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @covers \Sofascore\PurgatoryBundle\DependencyInjection\CompilerPass\RegisterPurgerImplementationCompilerPass
 */
class RegisterPurgerImplementationCompilerPassTest extends TestCase
{
    private ContainerBuilder $container;
    private RegisterPurgerImplementationCompilerPass $compilerPass;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->compilerPass = new RegisterPurgerImplementationCompilerPass();
    }

    protected function tearDown(): void
    {
        unset(
            $this->container,
            $this->compilerPass,
        );
    }

    public function testCompilerPassProcess(): void
    {
        $this->container->setParameter('sofascore.purgatory.purger', 'purger_test');

        self::assertTrue($this->container->hasParameter('sofascore.purgatory.purger'));
        self::assertFalse($this->container->hasAlias('sofascore.purgatory.purger'));

        $this->container->addDefinitions(['purger_test' => new Definition(PurgerInterface::class)]);
        $this->compilerPass->process($this->container);

        self::assertFalse($this->container->hasParameter('sofascore.purgatory.purger'));
        self::assertTrue($this->container->hasAlias('sofascore.purgatory.purger'));
    }

    public function testCompilerPassProcessClassNotImplementingInterface(): void
    {
        $this->container->setParameter('sofascore.purgatory.purger', 'purger_test');

        self::assertTrue($this->container->hasParameter('sofascore.purgatory.purger'));
        self::assertFalse($this->container->hasAlias('sofascore.purgatory.purger'));

        $this->container->addDefinitions(['purger_test' => new Definition(AnnotationsLoader::class)]);

        $this->expectException(\LogicException::class);
        $this->compilerPass->process($this->container);
    }
}
