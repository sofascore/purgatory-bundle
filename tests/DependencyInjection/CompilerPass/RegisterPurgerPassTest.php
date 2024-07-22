<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle2\DependencyInjection\CompilerPass\RegisterPurgerPass;
use Sofascore\PurgatoryBundle2\DependencyInjection\PurgatoryExtension;
use Sofascore\PurgatoryBundle2\Exception\RuntimeException;
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

    public function testDefaultPurgerIsSetToSymfonyPurgerIfHttpCacheStoreAndHostParamExist(): void
    {
        $this->container->setParameter('.sofascore.purgatory2.purger.host', 'localhost');

        (new RegisterPurgerPass())->process($this->container);

        self::assertSame('sofascore.purgatory2.purger.symfony', (string) $this->container->getAlias('sofascore.purgatory2.purger'));
        self::assertTrue($this->container->hasDefinition('sofascore.purgatory2.purger.symfony'));

        self::assertTrue($this->container->hasParameter('.sofascore.purgatory2.purger.name'));
        self::assertSame('symfony', $this->container->getParameter('.sofascore.purgatory2.purger.name'));
    }

    public function testDefaultPurgerIsSetToVoidPurgerIfHttpCacheStoreDoesNotExistAndHostParamDoes(): void
    {
        $this->container->setParameter('.sofascore.purgatory2.purger.host', 'localhost');
        $this->container->removeDefinition('http_cache.store');

        (new RegisterPurgerPass())->process($this->container);

        self::assertSame('sofascore.purgatory2.purger.void', (string) $this->container->getAlias('sofascore.purgatory2.purger'));
        self::assertFalse($this->container->hasDefinition('sofascore.purgatory2.purger.symfony'));

        self::assertTrue($this->container->hasParameter('.sofascore.purgatory2.purger.name'));
        self::assertSame('void', $this->container->getParameter('.sofascore.purgatory2.purger.name'));
    }

    public function testDefaultPurgerIsSetToVoidPurgerIfHttpCacheStoreExistsAndHostParamDoesNot(): void
    {
        (new RegisterPurgerPass())->process($this->container);

        self::assertSame('sofascore.purgatory2.purger.void', (string) $this->container->getAlias('sofascore.purgatory2.purger'));
        self::assertFalse($this->container->hasDefinition('sofascore.purgatory2.purger.symfony'));

        self::assertTrue($this->container->hasParameter('.sofascore.purgatory2.purger.name'));
        self::assertSame('void', $this->container->getParameter('.sofascore.purgatory2.purger.name'));
    }

    public function testRegisterPurgerWhenPurgerNameIsSetIfHttpCacheStoreAndHostParamExist(): void
    {
        $this->container->setParameter('.sofascore.purgatory2.purger.name', 'in-memory');
        $this->container->setParameter('.sofascore.purgatory2.purger.host', 'localhost');

        (new RegisterPurgerPass())->process($this->container);

        self::assertSame('sofascore.purgatory2.purger.in_memory', (string) $this->container->getAlias('sofascore.purgatory2.purger'));
        self::assertTrue($this->container->hasDefinition('sofascore.purgatory2.purger.symfony'));
    }

    public function testRegisterPurgerWhenPurgerNameIsSetIfHttpCacheStoreDoesNotExistAndHostParamDoes(): void
    {
        $this->container->setParameter('.sofascore.purgatory2.purger.name', 'in-memory');
        $this->container->setParameter('.sofascore.purgatory2.purger.host', 'localhost');
        $this->container->removeDefinition('http_cache.store');

        (new RegisterPurgerPass())->process($this->container);

        self::assertSame('sofascore.purgatory2.purger.in_memory', (string) $this->container->getAlias('sofascore.purgatory2.purger'));
        self::assertFalse($this->container->hasDefinition('sofascore.purgatory2.purger.symfony'));
    }

    public function testRegisterPurgerWhenPurgerNameIsSetIfHttpCacheStoreExistsAndHostParamDoesNot(): void
    {
        $this->container->setParameter('.sofascore.purgatory2.purger.name', 'in-memory');

        (new RegisterPurgerPass())->process($this->container);

        self::assertSame('sofascore.purgatory2.purger.in_memory', (string) $this->container->getAlias('sofascore.purgatory2.purger'));
        self::assertFalse($this->container->hasDefinition('sofascore.purgatory2.purger.symfony'));
    }

    public function testIdAsPurgerName(): void
    {
        $this->container->setParameter('.sofascore.purgatory2.purger.name', 'sofascore.purgatory2.purger.in_memory');

        (new RegisterPurgerPass())->process($this->container);

        self::assertSame('sofascore.purgatory2.purger.in_memory', (string) $this->container->getAlias('sofascore.purgatory2.purger'));
    }

    public function testExceptionIsThrownOnInvalidService(): void
    {
        $this->container->setParameter('.sofascore.purgatory2.purger.name', 'invalid');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The configured purger service "invalid" does not exist.');

        (new RegisterPurgerPass())->process($this->container);
    }
}
