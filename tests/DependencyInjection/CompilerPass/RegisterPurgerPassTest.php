<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle2\DependencyInjection\CompilerPass\RegisterPurgerPass;
use Sofascore\PurgatoryBundle2\DependencyInjection\PurgatoryExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\HttpCache\Store;

#[CoversClass(RegisterPurgerPass::class)]
final class RegisterPurgerPassTest extends TestCase
{
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
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
    }

    public function testDefaultPurgerIsSetToNullPurgerIfHttpCacheStoreDoesNotExist(): void
    {
        $this->container->removeDefinition('http_cache.store');

        (new RegisterPurgerPass())->process($this->container);

        self::assertSame('sofascore.purgatory.purger.null', (string) $this->container->getAlias('sofascore.purgatory.purger'));
        self::assertFalse($this->container->hasDefinition('sofascore.purgatory.purger.symfony'));
    }

    public function testRegisterPurgerWhenPurgerNameIsSetIfHttpCacheStoreExists(): void
    {
        $this->container->setParameter('.sofascore.purgatory.purger.name', 'in-memory');

        (new RegisterPurgerPass())->process($this->container);

        self::assertSame('sofascore.purgatory.purger.in_memory', (string) $this->container->getAlias('sofascore.purgatory.purger'));
        self::assertTrue($this->container->hasDefinition('sofascore.purgatory.purger.symfony'));
    }

    public function testRegisterPurgerWhenPurgerNameIsSetIfHttpCacheStoreDoesNotExist(): void
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
}
