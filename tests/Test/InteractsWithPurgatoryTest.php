<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle2\Purger\AsyncPurger;
use Sofascore\PurgatoryBundle2\Purger\InMemoryPurger;
use Sofascore\PurgatoryBundle2\Purger\PurgerInterface;
use Sofascore\PurgatoryBundle2\Purger\VoidPurger;
use Sofascore\PurgatoryBundle2\Test\InteractsWithPurgatory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(InteractsWithPurgatory::class)]
final class InteractsWithPurgatoryTest extends TestCase
{
    #[DataProvider('provideTraitTestCases')]
    public function testTrait(string $idInMemory, string $idAsync): void
    {
        $container = new Container();
        $container->set($idInMemory, new InMemoryPurger());
        $container->set($idAsync, new AsyncPurger($this->createMock(MessageBusInterface::class)));

        $test = new class($container) extends KernelTestCase {
            use InteractsWithPurgatory {
                _cleanUp as public;
            }

            private static Container $myContainer;

            public function __construct(Container $container)
            {
                self::$myContainer = $container;

                parent::__construct('name');
            }

            public function testUrlIsPurged(): void
            {
                $this->getPurger()->purge(['http://localhost/url']);
                $this->assertUrlIsPurged('http://localhost/url');
                $this->assertUrlIsPurged('/url');

                $this->assertUrlIsNotPurged('https://localhost/url');
                $this->assertUrlIsNotPurged('http://example.test/url');
                $this->assertUrlIsNotPurged('/url?foo=bar');
                $this->assertUrlIsNotPurged('/foo');

                $this->clearPurger();
                $this->assertNoUrlsArePurged();
            }

            protected static function getContainer(): Container
            {
                return self::$myContainer;
            }
        };

        $test->testUrlIsPurged();
        $test->_cleanUp();

        $test->testUrlIsPurged();
        $test->_cleanUp();
    }

    public static function provideTraitTestCases(): iterable
    {
        yield 'sync' => [PurgerInterface::class, 'sofascore.purgatory2.purger.async'];
        yield 'async' => ['sofascore.purgatory2.purger.sync', PurgerInterface::class];
    }

    public function testExceptionIsThrownWhenClassIsNotKernelTestCase(): void
    {
        $test = new class('name') extends TestCase {
            use InteractsWithPurgatory;

            public function testUrlIsPurged(): void
            {
                $this->getPurger();
            }
        };

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The "%s" trait can only be used with "%s".', InteractsWithPurgatory::class, KernelTestCase::class));

        $test->testUrlIsPurged();
    }

    public function testExceptionIsThrownWhenPurgerIsNotInMemory(): void
    {
        $test = new class('name') extends KernelTestCase {
            use InteractsWithPurgatory;

            public function testUrlIsPurged(): void
            {
                $this->getPurger();
            }

            protected static function getContainer(): Container
            {
                $container = new Container();
                $container->set(PurgerInterface::class, new VoidPurger());

                return $container;
            }
        };

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The "%s" trait can only be used if "InMemoryPurger" is set as the purger.', InteractsWithPurgatory::class));

        $test->testUrlIsPurged();
    }
}
