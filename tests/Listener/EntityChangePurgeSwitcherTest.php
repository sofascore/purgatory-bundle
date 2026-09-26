<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Listener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Listener\EntityChangePurgeSwitcher;

#[CoversClass(EntityChangePurgeSwitcher::class)]
final class EntityChangePurgeSwitcherTest extends TestCase
{
    public function testConfiguredDefault(): void
    {
        self::assertTrue((new EntityChangePurgeSwitcher())->isEnabled());
        self::assertTrue((new EntityChangePurgeSwitcher(true))->isEnabled());
        self::assertFalse((new EntityChangePurgeSwitcher(false))->isEnabled());
    }

    public function testWhileEnabled(): void
    {
        $switcher = new EntityChangePurgeSwitcher(false);

        $result = $switcher->whileEnabled(static function () use ($switcher): string {
            self::assertTrue($switcher->isEnabled());

            return 'foo';
        });

        self::assertSame('foo', $result);
        self::assertFalse($switcher->isEnabled());
    }

    public function testWhileDisabled(): void
    {
        $switcher = new EntityChangePurgeSwitcher(true);

        $result = $switcher->whileDisabled(static function () use ($switcher): string {
            self::assertFalse($switcher->isEnabled());

            return 'foo';
        });

        self::assertSame('foo', $result);
        self::assertTrue($switcher->isEnabled());
    }

    public function testNestedCallbacksRestorePreviousState(): void
    {
        $switcher = new EntityChangePurgeSwitcher(true);

        $switcher->whileDisabled(static function () use ($switcher): void {
            self::assertFalse($switcher->isEnabled());

            $switcher->whileEnabled(static function () use ($switcher): void {
                self::assertTrue($switcher->isEnabled());
            });

            self::assertFalse($switcher->isEnabled());
        });

        self::assertTrue($switcher->isEnabled());
    }

    public function testPreviousStateIsRestoredWhenCallbackThrows(): void
    {
        $switcher = new EntityChangePurgeSwitcher(true);
        $exception = new \RuntimeException();

        try {
            $switcher->whileDisabled(static function () use ($exception): never {
                throw $exception;
            });
            self::fail('The exception was not rethrown.');
        } catch (\RuntimeException $e) {
            self::assertSame($exception, $e);
        }

        self::assertTrue($switcher->isEnabled());
    }

    public function testInstancesAreIndependent(): void
    {
        $switcher = new EntityChangePurgeSwitcher(true);
        $other = new EntityChangePurgeSwitcher(true);

        $switcher->whileDisabled(static function () use ($other): void {
            self::assertTrue($other->isEnabled());
        });
    }
}
