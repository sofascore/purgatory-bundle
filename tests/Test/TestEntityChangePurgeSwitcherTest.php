<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Test\TestEntityChangePurgeSwitcher;

#[CoversClass(TestEntityChangePurgeSwitcher::class)]
final class TestEntityChangePurgeSwitcherTest extends TestCase
{
    protected function tearDown(): void
    {
        TestEntityChangePurgeSwitcher::resetGlobally();

        parent::tearDown();
    }

    public function testConfiguredDefault(): void
    {
        self::assertTrue((new TestEntityChangePurgeSwitcher())->isEnabled());
        self::assertTrue((new TestEntityChangePurgeSwitcher(true))->isEnabled());
        self::assertFalse((new TestEntityChangePurgeSwitcher(false))->isEnabled());
    }

    public function testGlobalOverride(): void
    {
        $enabledByDefault = new TestEntityChangePurgeSwitcher(true);
        $disabledByDefault = new TestEntityChangePurgeSwitcher(false);

        self::assertNull(TestEntityChangePurgeSwitcher::getGlobalOverride());

        TestEntityChangePurgeSwitcher::enableGlobally();

        self::assertTrue(TestEntityChangePurgeSwitcher::getGlobalOverride());
        self::assertTrue($enabledByDefault->isEnabled());
        self::assertTrue($disabledByDefault->isEnabled());

        TestEntityChangePurgeSwitcher::disableGlobally();

        self::assertFalse(TestEntityChangePurgeSwitcher::getGlobalOverride());
        self::assertFalse($enabledByDefault->isEnabled());
        self::assertFalse($disabledByDefault->isEnabled());

        TestEntityChangePurgeSwitcher::resetGlobally();

        self::assertNull(TestEntityChangePurgeSwitcher::getGlobalOverride());
        self::assertTrue($enabledByDefault->isEnabled());
        self::assertFalse($disabledByDefault->isEnabled());
    }

    public function testCallbacksTakePrecedenceOverGlobalOverride(): void
    {
        $switcher = new TestEntityChangePurgeSwitcher(false);

        TestEntityChangePurgeSwitcher::enableGlobally();

        $switcher->whileDisabled(static function () use ($switcher): void {
            self::assertFalse($switcher->isEnabled());
        });

        self::assertTrue($switcher->isEnabled());

        TestEntityChangePurgeSwitcher::disableGlobally();

        $switcher->whileEnabled(static function () use ($switcher): void {
            self::assertTrue($switcher->isEnabled());
        });

        self::assertFalse($switcher->isEnabled());
    }

    public function testEnableAndDisableTakePrecedenceOverGlobalOverride(): void
    {
        $switcher = new TestEntityChangePurgeSwitcher(true);

        TestEntityChangePurgeSwitcher::enableGlobally();

        $switcher->disable();

        self::assertFalse($switcher->isEnabled());

        TestEntityChangePurgeSwitcher::disableGlobally();

        $switcher->enable();

        self::assertTrue($switcher->isEnabled());
    }

    public function testResetKeepsGlobalOverride(): void
    {
        $switcher = new TestEntityChangePurgeSwitcher(false);

        TestEntityChangePurgeSwitcher::enableGlobally();

        $switcher->disable();
        $switcher->reset();

        self::assertTrue($switcher->isEnabled());
        self::assertTrue(TestEntityChangePurgeSwitcher::getGlobalOverride());
    }

    public function testCallbacksTakePrecedenceOverEnableAndDisable(): void
    {
        $switcher = new TestEntityChangePurgeSwitcher(true);

        $switcher->disable();

        $switcher->whileEnabled(static function () use ($switcher): void {
            self::assertTrue($switcher->isEnabled());

            // takes effect once the callback is done
            $switcher->disable();

            self::assertTrue($switcher->isEnabled());
        });

        self::assertFalse($switcher->isEnabled());
    }
}
