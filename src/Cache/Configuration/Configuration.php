<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Cache\Configuration;

use Sofascore\PurgatoryBundle\Listener\Enum\Action;

final class Configuration implements \Countable
{
    /**
     * @param array<non-empty-string, list<array{
     *     routeName: string,
     *     routeParams?: array<string, array{type: string, values: list<mixed>, optional?: true}>,
     *     if?: string,
     *     actions?: non-empty-list<Action>,
     *     context?: array<string, ?scalar>,
     * }>> $configuration
     */
    public function __construct(
        private readonly array $configuration,
    ) {
    }

    /**
     * @param non-empty-string $key
     */
    public function has(string $key): bool
    {
        return isset($this->configuration[$key]);
    }

    /**
     * @param non-empty-string $key
     */
    public function get(string $key): Subscriptions
    {
        return $this->has($key)
            ? new Subscriptions($key, $this->configuration[$key])
            : throw new \OutOfBoundsException(\sprintf('No subscriptions found for the key "%s".', $key));
    }

    /**
     * @return list<non-empty-string>
     */
    public function keys(): array
    {
        return array_keys($this->configuration);
    }

    public function count(): int
    {
        return \count($this->configuration);
    }

    /**
     * @return array<non-empty-string, list<array{
     *     routeName: string,
     *     routeParams?: array<string, array{type: string, values: list<mixed>, optional?: true}>,
     *     if?: string,
     *     actions?: non-empty-list<Action>,
     *     context?: array<string, ?scalar>,
     * }>>
     */
    public function toArray(): array
    {
        return $this->configuration;
    }
}
