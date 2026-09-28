<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\DependencyInjection\RegisterPurgerCompilerPass;
use Sofascore\PurgatoryBundle\Exception\RuntimeException;
use Sofascore\PurgatoryBundle\PurgatoryBundle;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\HttpCache\Store;

#[CoversClass(RegisterPurgerCompilerPass::class)]
final class RegisterPurgerCompilerPassTest extends TestCase
{
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->container->setParameter('kernel.project_dir', __DIR__);
        $this->container->setParameter('kernel.build_dir', __DIR__);
        $this->container->setParameter('kernel.environment', 'dev');
        $this->container->register('http_cache.store', Store::class);

        (new PurgatoryBundle())->getContainerExtension()->load([], $this->container);
    }

    protected function tearDown(): void
    {
        unset($this->container);
    }

    public function testDefaultPurgerIsSetToSymfonyPurgerIfHttpCacheStoreExists(): void
    {
        (new RegisterPurgerCompilerPass())->process($this->container);

        self::assertSame('sofascore.purgatory.purger.symfony', (string) $this->container->getAlias('sofascore.purgatory.purger'));
        self::assertTrue($this->container->hasDefinition('sofascore.purgatory.purger.symfony'));

        self::assertTrue($this->container->hasParameter('.sofascore.purgatory.purger.name'));
        self::assertSame('symfony', $this->container->getParameter('.sofascore.purgatory.purger.name'));
    }

    public function testDefaultPurgerIsSetToVoidPurgerIfHttpCacheStoreDoesNotExist(): void
    {
        $this->container->removeDefinition('http_cache.store');

        (new RegisterPurgerCompilerPass())->process($this->container);

        self::assertSame('sofascore.purgatory.purger.void', (string) $this->container->getAlias('sofascore.purgatory.purger'));
        self::assertFalse($this->container->hasDefinition('sofascore.purgatory.purger.symfony'));

        self::assertTrue($this->container->hasParameter('.sofascore.purgatory.purger.name'));
        self::assertSame('void', $this->container->getParameter('.sofascore.purgatory.purger.name'));
    }

    public function testRegisterPurgerWhenPurgerNameIsSetAndHttpCacheStoreExists(): void
    {
        $this->container->setParameter('.sofascore.purgatory.purger.name', 'in-memory');

        (new RegisterPurgerCompilerPass())->process($this->container);

        self::assertSame('sofascore.purgatory.purger.in_memory', (string) $this->container->getAlias('sofascore.purgatory.purger'));
        self::assertTrue($this->container->hasDefinition('sofascore.purgatory.purger.symfony'));
    }

    public function testRegisterPurgerWhenPurgerNameIsSetAndHttpCacheStoreDoesNotExist(): void
    {
        $this->container->setParameter('.sofascore.purgatory.purger.name', 'in-memory');
        $this->container->removeDefinition('http_cache.store');

        (new RegisterPurgerCompilerPass())->process($this->container);

        self::assertSame('sofascore.purgatory.purger.in_memory', (string) $this->container->getAlias('sofascore.purgatory.purger'));
        self::assertFalse($this->container->hasDefinition('sofascore.purgatory.purger.symfony'));
    }

    public function testIdAsPurgerName(): void
    {
        $this->container->setParameter('.sofascore.purgatory.purger.name', 'sofascore.purgatory.purger.in_memory');

        (new RegisterPurgerCompilerPass())->process($this->container);

        self::assertSame('sofascore.purgatory.purger.in_memory', (string) $this->container->getAlias('sofascore.purgatory.purger'));
    }

    public function testExceptionIsThrownOnInvalidService(): void
    {
        $this->container->setParameter('.sofascore.purgatory.purger.name', 'invalid');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The configured purger service "invalid" does not exist.');

        (new RegisterPurgerCompilerPass())->process($this->container);
    }

    public function testChainPurgerIsRemovedWhenNotUsed(): void
    {
        (new RegisterPurgerCompilerPass())->process($this->container);

        self::assertFalse($this->container->hasDefinition('sofascore.purgatory.purger.chain'));
    }

    public function testChainPurger(): void
    {
        $this->container->register('foo_purger');
        $this->container->setParameter('.sofascore.purgatory.purger.name', 'chain');
        $this->container->setParameter('.sofascore.purgatory.purger.chain', [
            ['name' => 'in-memory', 'hosts' => [], 'http_client' => null],
            ['name' => 'foo_purger', 'hosts' => [], 'http_client' => null],
            ['name' => 'varnish', 'hosts' => [], 'http_client' => null],
        ]);

        (new RegisterPurgerCompilerPass())->process($this->container);

        self::assertSame('sofascore.purgatory.purger.chain', (string) $this->container->getAlias('sofascore.purgatory.purger'));

        $argument = $this->container->getDefinition('sofascore.purgatory.purger.chain')->getArgument(0);

        self::assertInstanceOf(IteratorArgument::class, $argument);
        self::assertSame(
            ['sofascore.purgatory.purger.in_memory', 'foo_purger', 'sofascore.purgatory.purger.varnish'],
            array_map('strval', $argument->getValues()),
        );
    }

    public function testChainPurgerWithVarnishOptions(): void
    {
        $this->container->setParameter('.sofascore.purgatory.purger.name', 'chain');
        $this->container->setParameter('.sofascore.purgatory.purger.chain', [
            ['name' => 'varnish', 'hosts' => ['http://foo.bar'], 'http_client' => 'foo.client'],
            ['name' => 'varnish', 'hosts' => ['http://baz.qux'], 'http_client' => null],
            ['name' => 'sofascore.purgatory.purger.varnish', 'hosts' => [], 'http_client' => 'bar.client'],
        ]);

        (new RegisterPurgerCompilerPass())->process($this->container);

        $argument = $this->container->getDefinition('sofascore.purgatory.purger.chain')->getArgument(0);

        self::assertInstanceOf(IteratorArgument::class, $argument);
        self::assertSame(
            ['sofascore.purgatory.purger.chain.0', 'sofascore.purgatory.purger.chain.1', 'sofascore.purgatory.purger.chain.2'],
            array_map('strval', $argument->getValues()),
        );

        $expectedArguments = [
            ['index_0' => 'foo.client', 'index_1' => ['http://foo.bar']],
            ['index_1' => ['http://baz.qux']],
            ['index_0' => 'bar.client', 'index_1' => []],
        ];

        foreach ($expectedArguments as $index => $expected) {
            $definition = $this->container->getDefinition('sofascore.purgatory.purger.chain.'.$index);

            self::assertInstanceOf(ChildDefinition::class, $definition);
            self::assertSame('sofascore.purgatory.purger.varnish', $definition->getParent());

            $arguments = array_map(static fn (mixed $argument): mixed => $argument instanceof Reference ? (string) $argument : $argument, $definition->getArguments());
            ksort($arguments);

            self::assertSame($expected, $arguments);
        }
    }

    public function testExceptionIsThrownWhenVarnishOptionsAreSetForOtherPurgerInPurgerChain(): void
    {
        $this->container->setParameter('.sofascore.purgatory.purger.name', 'chain');
        $this->container->setParameter('.sofascore.purgatory.purger.chain', [
            ['name' => 'in-memory', 'hosts' => ['http://foo.bar'], 'http_client' => null],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The "hosts" and "http_client" options in the purger chain can only be used with the Varnish purger, but they were set for "in-memory".');

        (new RegisterPurgerCompilerPass())->process($this->container);
    }

    public function testExceptionIsThrownOnInvalidServiceInPurgerChain(): void
    {
        $this->container->setParameter('.sofascore.purgatory.purger.name', 'chain');
        $this->container->setParameter('.sofascore.purgatory.purger.chain', [
            ['name' => 'in-memory', 'hosts' => [], 'http_client' => null],
            ['name' => 'invalid', 'hosts' => [], 'http_client' => null],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The configured purger service "invalid" does not exist.');

        (new RegisterPurgerCompilerPass())->process($this->container);
    }

    public function testExceptionIsThrownWhenChainPurgerIsPartOfPurgerChain(): void
    {
        $this->container->setParameter('.sofascore.purgatory.purger.name', 'chain');
        $this->container->setParameter('.sofascore.purgatory.purger.chain', [
            ['name' => 'sofascore.purgatory.purger.chain', 'hosts' => [], 'http_client' => null],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The chain purger cannot be part of the purger chain.');

        (new RegisterPurgerCompilerPass())->process($this->container);
    }

    public function testExceptionIsThrownWhenPurgerChainIsEmpty(): void
    {
        $this->container->setParameter('.sofascore.purgatory.purger.name', 'chain');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The chain purger requires at least one purger to be defined in the "chain" option.');

        (new RegisterPurgerCompilerPass())->process($this->container);
    }
}
