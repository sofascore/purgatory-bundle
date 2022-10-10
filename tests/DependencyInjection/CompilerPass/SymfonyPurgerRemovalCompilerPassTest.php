<?php

declare(strict_types=1);

namespace SofaScore\Purgatory\Tests\DependencyInjection\CompilerPass;

use PHPUnit\Framework\TestCase;
use SofaScore\Purgatory\DependencyInjection\CompilerPass\SymfonyPurgerRemovalCompilerPass;
use SofaScore\Purgatory\Purger\SymfonyPurger;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @covers \SofaScore\Purgatory\DependencyInjection\CompilerPass\SymfonyPurgerRemovalCompilerPass
 */
class SymfonyPurgerRemovalCompilerPassTest extends TestCase
{
    private ContainerBuilder $container;
    private SymfonyPurgerRemovalCompilerPass $compilerPass;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->compilerPass = new SymfonyPurgerRemovalCompilerPass();
    }

    public function testCompilerPassProcessWithoutExistingHttpCache(): void
    {
        $this->container->addDefinitions(['sofascore.purgatory.purger.symfony' => new Definition(SymfonyPurger::class)]);

        self::assertTrue($this->container->hasDefinition('sofascore.purgatory.purger.symfony'));

        $this->compilerPass->process($this->container);

        self::assertFalse($this->container->hasDefinition('sofascore.purgatory.purger.symfony'));
    }

    public function testCompilerPassProcessWithExistingHttpCache(): void
    {
        $this->container->addDefinitions([
            'http_cache.store' => new Definition('TestClass'),
            'sofascore.purgatory.purger.symfony' => new Definition(SymfonyPurger::class)
        ]);

        self::assertTrue($this->container->hasDefinition('sofascore.purgatory.purger.symfony'));

        $this->compilerPass->process($this->container);

        self::assertTrue($this->container->hasDefinition('sofascore.purgatory.purger.symfony'));
    }
}
