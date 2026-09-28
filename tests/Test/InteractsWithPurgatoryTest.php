<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Listener\EntityChangePurgeSwitcher;
use Sofascore\PurgatoryBundle\Listener\EntityChangePurgeSwitcherInterface;
use Sofascore\PurgatoryBundle\Purger\AsyncPurger;
use Sofascore\PurgatoryBundle\Purger\InMemoryPurger;
use Sofascore\PurgatoryBundle\Purger\PurgeRequest;
use Sofascore\PurgatoryBundle\Purger\PurgerInterface;
use Sofascore\PurgatoryBundle\Purger\VoidPurger;
use Sofascore\PurgatoryBundle\RouteProvider\PurgeRoute;
use Sofascore\PurgatoryBundle\Test\InteractsWithPurgatory;
use Sofascore\PurgatoryBundle\Test\TestEntityChangePurgeSwitcher;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Messenger\MessageBusInterface;

final class InteractsWithPurgatoryTest extends TestCase
{
    protected function tearDown(): void
    {
        TestEntityChangePurgeSwitcher::resetGlobally();

        parent::tearDown();
    }

    #[DataProvider('provideTraitTestCases')]
    public function testTrait(string $idInMemory, string $idAsync): void
    {
        $container = new Container();
        $container->set($idInMemory, new InMemoryPurger());
        $container->set($idAsync, new AsyncPurger(self::createStub(MessageBusInterface::class)));

        $test = new class('name') extends KernelTestCase {
            use InteractsWithPurgatory;

            public static Container $myContainer;

            public function testUrlIsPurged(): void
            {
                self::getPurger()->purge([
                    new PurgeRequest('http://localhost/url', new PurgeRoute('route_url', [])),
                ]);
                self::assertUrlIsPurged('http://localhost/url');
                self::assertUrlIsPurged('/url');

                self::assertUrlIsNotPurged('https://localhost/url');
                self::assertUrlIsNotPurged('http://example.test/url');
                self::assertUrlIsNotPurged('/url?foo=bar');
                self::assertUrlIsNotPurged('/foo');

                self::assertSame(['http://localhost/url'], self::getPurgedUrls(true));
                self::assertSame(['/url'], self::getPurgedUrls(false));

                self::clearPurger();
                self::assertNoUrlsArePurged();
            }

            protected static function getContainer(): Container
            {
                return self::$myContainer;
            }
        };

        $test::$myContainer = $container;

        $test->testUrlIsPurged();
    }

    public static function provideTraitTestCases(): iterable
    {
        yield 'sync' => [PurgerInterface::class, 'sofascore.purgatory.purger.async'];
        yield 'async' => ['sofascore.purgatory.purger.sync', PurgerInterface::class];
    }

    public function testExceptionIsThrownWhenClassIsNotKernelTestCase(): void
    {
        $test = new class('name') extends TestCase {
            use InteractsWithPurgatory;

            public function testUrlIsPurged(): void
            {
                self::getPurger();
            }
        };

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(\sprintf('The "%s" trait can only be used with "%s".', InteractsWithPurgatory::class, KernelTestCase::class));

        $test->testUrlIsPurged();
    }

    public function testExceptionIsThrownWhenPurgerIsNotInMemory(): void
    {
        $test = new class('name') extends KernelTestCase {
            use InteractsWithPurgatory;

            public function testUrlIsPurged(): void
            {
                self::getPurger();
            }

            protected static function getContainer(): Container
            {
                $container = new Container();
                $container->set(PurgerInterface::class, new VoidPurger());

                return $container;
            }
        };

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(\sprintf('The "%s" trait can only be used if "InMemoryPurger" is set as the purger.', InteractsWithPurgatory::class));

        $test->testUrlIsPurged();
    }

    public function testExceptionIsThrownWhenGlobalOverrideHasNoEffect(): void
    {
        $test = new class('name') extends KernelTestCase {
            use InteractsWithPurgatory;

            public function testUrlIsPurged(): void
            {
                self::getPurger();
            }

            protected static function getContainer(): Container
            {
                $container = new Container();
                $container->set(PurgerInterface::class, new InMemoryPurger());
                $container->set(EntityChangePurgeSwitcherInterface::class, new EntityChangePurgeSwitcher(false));

                return $container;
            }
        };

        TestEntityChangePurgeSwitcher::enableGlobally();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(\sprintf('The global override of "%s", e.g. by the "#[WithEntityChangePurging]" attribute, has no effect unless the "test" option is enabled.', TestEntityChangePurgeSwitcher::class));

        $test->testUrlIsPurged();
    }

    public function testGlobalOverrideIsAllowedWithTestSwitcher(): void
    {
        $test = new class('name') extends KernelTestCase {
            use InteractsWithPurgatory;

            public function testUrlIsPurged(): void
            {
                self::assertNoUrlsArePurged();
            }

            protected static function getContainer(): Container
            {
                $container = new Container();
                $container->set(PurgerInterface::class, new InMemoryPurger());
                $container->set(EntityChangePurgeSwitcherInterface::class, new TestEntityChangePurgeSwitcher(false));

                return $container;
            }
        };

        TestEntityChangePurgeSwitcher::enableGlobally();

        $test->testUrlIsPurged();
    }

    #[TestWith(['assertNoUrlsArePurged', []])]
    #[TestWith(['assertUrlIsNotPurged', ['/foo']])]
    public function testExceptionIsThrownWhenAssertingNoPurgesWhilePurgingIsDisabled(string $assertion, array $arguments): void
    {
        $test = self::createTestWithSwitcher(new EntityChangePurgeSwitcher(false));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Asserting that URLs were not purged has no effect while purging on entity changes is disabled, enable it with the "#[WithEntityChangePurging]" attribute or make the assertion inside the "whileEnabled()" callback of the switcher.');

        $test->callAssertion($assertion, $arguments);
    }

    #[TestWith(['assertNoUrlsArePurged', []])]
    #[TestWith(['assertUrlIsNotPurged', ['/foo']])]
    public function testAssertingNoPurgesInsideWhileEnabled(string $assertion, array $arguments): void
    {
        $switcher = new EntityChangePurgeSwitcher(false);
        $test = self::createTestWithSwitcher($switcher);

        $switcher->whileEnabled(static fn () => $test->callAssertion($assertion, $arguments));

        $this->addToAssertionCount(1);
    }

    private static function createTestWithSwitcher(EntityChangePurgeSwitcherInterface $switcher): KernelTestCase
    {
        $test = new class('name') extends KernelTestCase {
            use InteractsWithPurgatory;

            public static EntityChangePurgeSwitcherInterface $switcher;

            public function callAssertion(string $assertion, array $arguments): void
            {
                self::{$assertion}(...$arguments);
            }

            protected static function getContainer(): Container
            {
                $container = new Container();
                $container->set(PurgerInterface::class, new InMemoryPurger());
                $container->set(EntityChangePurgeSwitcherInterface::class, self::$switcher);

                return $container;
            }
        };

        $test::$switcher = $switcher;

        return $test;
    }
}
