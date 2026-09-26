<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Listener;

/**
 * Controls whether entity changes trigger purge requests.
 *
 * Since the service is shared, the callbacks affect all entity changes flushed while they run.
 */
interface EntityChangePurgeSwitcherInterface
{
    public function isEnabled(): bool;

    /**
     * Enables purging while the callback runs, then restores the previous state.
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public function whileEnabled(callable $callback): mixed;

    /**
     * Disables purging while the callback runs, then restores the previous state.
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public function whileDisabled(callable $callback): mixed;
}
