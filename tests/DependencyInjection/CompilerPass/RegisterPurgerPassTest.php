<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\DependencyInjection\CompilerPass\RegisterPurgerPass;
use Sofascore\PurgatoryBundle\DependencyInjection\PurgatoryExtension;
use Sofascore\PurgatoryBundle\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\HttpCache\Store;

#[CoversClass(RegisterPurgerPass::class)]
final class RegisterPurgerPassTest extends TestCase
{
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->container->setParameter('kernel.project_dir', __DIR__);
        $this->container->register('http_cache.store', Store::class);

        (new PurgatoryExtension())->load([], $this->container);
    }

    protected function tearDown(): void
    {
        unset($this->container);
    }

    public function testDefaultPurgerIsSetToSymfonyPurgerIfHttpCacheStoreExists(): void
    {
        (new RegisterPurgerPass())->process($this->container);

        self::assertSame('sofascore.purgatory.purger.symfony', (string) $this->container->getAlias('sofascore.purgatory.purger'));
        self::assertTrue($this->container->hasDefinition('sofascore.purgatory.purger.symfony'));

        self::assertTrue($this->container->hasParameter('.sofascore.purgatory.purger.name'));
        self::assertSame('symfony', $this->container->getParameter('.sofascore.purgatory.purger.name'));
    }

    public function testDefaultPurgerIsSetToVoidPurgerIfHttpCacheStoreDoesNotExist(): void
    {
        $this->container->removeDefinition('http_cache.store');

        (new RegisterPurgerPass())->process($this->container);

        self::assertSame('sofascore.purgatory.purger.void', (string) $this->container->getAlias('sofascore.purgatory.purger'));
        self::assertFalse($this->container->hasDefinition('sofascore.purgatory.purger.symfony'));

        self::assertTrue($this->container->hasParameter('.sofascore.purgatory.purger.name'));
        self::assertSame('void', $this->container->getParameter('.sofascore.purgatory.purger.name'));
    }

    public function testRegisterPurgerWhenPurgerNameIsSetAndHttpCacheStoreExists(): void
    {
        $this->container->setParameter('.sofascore.purgatory.purger.name', 'in-memory');

        (new RegisterPurgerPass())->process($this->container);

        self::assertSame('sofascore.purgatory.purger.in_memory', (string) $this->container->getAlias('sofascore.purgatory.purger'));
        self::assertTrue($this->container->hasDefinition('sofascore.purgatory.purger.symfony'));
    }

    public function testRegisterPurgerWhenPurgerNameIsSetAndHttpCacheStoreDoesNotExist(): void
    {
        $this->container->setParameter('.sofascore.purgatory.purger.name', 'in-memory');
        $this->container->removeDefinition('http_cache.store');

        (new RegisterPurgerPass())->process($this->container);

        self::assertSame('sofascore.purgatory.purger.in_memory', (string) $this->container->getAlias('sofascore.purgatory.purger'));
        self::assertFalse($this->container->hasDefinition('sofascore.purgatory.purger.symfony'));
    }

    public function testIdAsPurgerName(): void
    {
        $this->container->setParameter('.sofascore.purgatory.purger.name', 'sofascore.purgatory.purger.in_memory');

        (new RegisterPurgerPass())->process($this->container);

        self::assertSame('sofascore.purgatory.purger.in_memory', (string) $this->container->getAlias('sofascore.purgatory.purger'));
    }

    public function testExceptionIsThrownOnInvalidService(): void
    {
        $this->container->setParameter('.sofascore.purgatory.purger.name', 'invalid');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The configured purger service "invalid" does not exist.');

        (new RegisterPurgerPass())->process($this->container);
    }
}
