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
        TestEntityChangePurgeSwitcher::reset();

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

        TestEntityChangePurgeSwitcher::enable();

        self::assertTrue(TestEntityChangePurgeSwitcher::getGlobalOverride());
        self::assertTrue($enabledByDefault->isEnabled());
        self::assertTrue($disabledByDefault->isEnabled());

        TestEntityChangePurgeSwitcher::disable();

        self::assertFalse(TestEntityChangePurgeSwitcher::getGlobalOverride());
        self::assertFalse($enabledByDefault->isEnabled());
        self::assertFalse($disabledByDefault->isEnabled());

        TestEntityChangePurgeSwitcher::reset();

        self::assertNull(TestEntityChangePurgeSwitcher::getGlobalOverride());
        self::assertTrue($enabledByDefault->isEnabled());
        self::assertFalse($disabledByDefault->isEnabled());
    }

    public function testCallbacksTakePrecedenceOverGlobalOverride(): void
    {
        $switcher = new TestEntityChangePurgeSwitcher(false);

        TestEntityChangePurgeSwitcher::enable();

        $switcher->whileDisabled(static function () use ($switcher): void {
            self::assertFalse($switcher->isEnabled());
        });

        self::assertTrue($switcher->isEnabled());

        TestEntityChangePurgeSwitcher::disable();

        $switcher->whileEnabled(static function () use ($switcher): void {
            self::assertTrue($switcher->isEnabled());
        });

        self::assertFalse($switcher->isEnabled());
    }
}
