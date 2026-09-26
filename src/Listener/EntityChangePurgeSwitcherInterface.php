<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Listener;

use Symfony\Contracts\Service\ResetInterface;

/**
 * Controls whether entity changes trigger purge requests.
 */
interface EntityChangePurgeSwitcherInterface extends ResetInterface
{
    public function isEnabled(): bool;

    /**
     * Enables purging until reset() is called.
     */
    public function enable(): void;

    /**
     * Disables purging until reset() is called.
     */
    public function disable(): void;

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
