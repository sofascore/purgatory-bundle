<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle2\Purger\InMemoryPurger;
use Sofascore\PurgatoryBundle2\Purger\PurgerInterface;
use Sofascore\PurgatoryBundle2\Purger\VoidPurger;
use Sofascore\PurgatoryBundle2\Test\InteractsWithPurgatory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Container;

#[CoversClass(InteractsWithPurgatory::class)]
final class InteractsWithPurgatoryTest extends TestCase
{
    public function testTrait(): void
    {
        $test = new class('name') extends KernelTestCase {
            use InteractsWithPurgatory {
                _cleanUp as public;
            }

            public function testUrlIsPurged(): void
            {
                $this->getPurger()->purge(['/url']);
                $this->assertUrlIsPurged('/url');

                $this->clearPurger();
                $this->assertUrlIsNotPurged('/url');
            }

            protected static function getContainer(): Container
            {
                $container = new Container();
                $container->set(PurgerInterface::class, new InMemoryPurger());

                return $container;
            }
        };

        $test->testUrlIsPurged();
        $test->_cleanUp();

        $test->testUrlIsPurged();
        $test->_cleanUp();
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
