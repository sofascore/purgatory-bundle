<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Listener;

final class EntityChangePurgeSwitcher implements EntityChangePurgeSwitcherInterface
{
    private ?bool $override = null;

    public function __construct(
        private readonly bool $enabled = true,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->override ?? $this->enabled;
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
