<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Test;

use Sofascore\PurgatoryBundle\Listener\EntityChangePurgeSwitcherInterface;

/**
 * Implementation used when the "test" option is enabled. Its state can also
 * be overridden globally, which applies to every kernel booted while a test runs.
 */
final class TestEntityChangePurgeSwitcher implements EntityChangePurgeSwitcherInterface
{
    private static ?bool $globalOverride = null;

    private ?bool $override = null;

    public function __construct(
        private readonly bool $enabled = true,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->override ?? self::$globalOverride ?? $this->enabled;
    }

    /**
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public function whileEnabled(callable $callback): mixed
    {
        return $this->runWith(true, $callback);
    }

    /**
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public function whileDisabled(callable $callback): mixed
    {
        return $this->runWith(false, $callback);
    }

    public static function enable(): void
    {
        self::$globalOverride = true;
    }

    public static function disable(): void
    {
        self::$globalOverride = false;
    }

    /**
     * Restores the configured default.
     */
    public static function reset(): void
    {
        self::$globalOverride = null;
    }

    /**
     * @internal
     */
    public static function getGlobalOverride(): ?bool
    {
        return self::$globalOverride;
    }

    /**
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    private function runWith(bool $override, callable $callback): mixed
    {
        $previous = $this->override;
        $this->override = $override;

        try {
            return $callback();
        } finally {
            $this->override = $previous;
        }
    }
}
