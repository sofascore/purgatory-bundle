<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Listener;

use Symfony\Contracts\Service\ResetInterface;

/**
 * Controls whether entity changes trigger purge requests.
 *
 * Since the service is shared, a change applies to all entity changes flushed
 * afterwards. The service is reset after each request and message, restoring
 * the configured default.
 */
final class EntityChangePurgeSwitcher implements ResetInterface
{
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
        return $this->scopedOverride ?? $this->override ?? $this->enabled;
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
     * Enables purging while the callback runs, then restores the previous state.
     *
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
     * Disables purging while the callback runs, then restores the previous state.
     *
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
