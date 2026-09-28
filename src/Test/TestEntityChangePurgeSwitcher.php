<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Test;

use Sofascore\PurgatoryBundle\Listener\EntityChangePurgeSwitcherInterface;

/**
 * Implementation used when the "test" option is enabled. Its state can also
 * be overridden globally, which applies to every kernel booted while a test
 * runs and isn't cleared when the service is reset.
 */
final class TestEntityChangePurgeSwitcher implements EntityChangePurgeSwitcherInterface
{
    private static ?bool $globalOverride = null;

    private ?bool $override = null;

    /**
     * Set by the callbacks, which restore it themselves and take precedence while they run.
     */
    private ?bool $scopedOverride = null;

    public function __construct(
        private readonly bool $enabled = true,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->scopedOverride ?? $this->override ?? self::$globalOverride ?? $this->enabled;
    }

    public function enable(): void
    {
        $this->override = true;
    }

    public function disable(): void
    {
        $this->override = false;
    }

    public function reset(): void
    {
        $this->override = null;
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

    public static function enableGlobally(): void
    {
        self::$globalOverride = true;
    }

    public static function disableGlobally(): void
    {
        self::$globalOverride = false;
    }

    /**
     * Restores the configured default.
     */
    public static function resetGlobally(): void
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
        $previous = $this->scopedOverride;
        $this->scopedOverride = $override;

        try {
            return $callback();
        } finally {
            $this->scopedOverride = $previous;
        }
    }
}
