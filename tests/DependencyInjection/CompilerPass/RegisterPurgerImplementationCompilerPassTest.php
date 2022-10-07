<?php

declare(strict_types=1);

namespace SofaScore\Purgatory\Tests\DependencyInjection\CompilerPass;

use PHPUnit\Framework\TestCase;
use SofaScore\Purgatory\DependencyInjection\CompilerPass\RegisterPurgerImplementationCompilerPass;
use SofaScore\Purgatory\Mapping\Loader\AnnotationsLoader;
use SofaScore\Purgatory\Purger\PurgerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @covers \SofaScore\Purgatory\DependencyInjection\CompilerPass\RegisterPurgerImplementationCompilerPass
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
