<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Cache\Configuration;

use Sofascore\PurgatoryBundle\Listener\Enum\Action;

/**
 * @implements \IteratorAggregate<int, array{
 *     routeName: string,
 *     routeParams?: array<string, array{type: string, values: list<mixed>, optional?: true}>,
 *     if?: string,
 *     actions?: non-empty-list<Action>,
 * }>
 */
final class Subscriptions implements \IteratorAggregate, \Countable
{
    /**
     * @param non-empty-string $key
     * @param list<array{
     *     routeName: string,
     *     routeParams?: array<string, array{type: string, values: list<mixed>, optional?: true}>,
     *     if?: string,
     *     actions?: non-empty-list<Action>,
     * }> $subscriptions
     */
    public function __construct(
        private readonly string $key,
        private readonly array $subscriptions,
    ) {
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->subscriptions);
    }

    public function count(): int
    {
        return \count($this->subscriptions);
    }

    /**
     * @return non-empty-string
     */
    public function key(): string
    {
        return $this->key;
    }

    /**
     * @return list<array{
     *     routeName: string,
     *     routeParams?: array<string, array{type: string, values: list<mixed>, optional?: true}>,
     *     if?: string,
     *     actions?: non-empty-list<Action>,
     * }>
     */
    public function toArray(): array
    {
        return $this->subscriptions;
    }
}
