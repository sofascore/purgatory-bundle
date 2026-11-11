<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Purger;

use Sofascore\PurgatoryBundle\RouteProvider\PurgeRoute;

final class PurgeRequest
{
    /**
     * The `context` of every PurgeOn that led to this URL, without duplicates. An empty array stands for
     * a subscription without context.
     *
     * @var non-empty-list<array<array-key, mixed>>
     */
    public readonly array $contexts;

    /**
     * @param ?non-empty-list<array<array-key, mixed>> $contexts defaults to the context of the given route
     */
    public function __construct(
        public readonly string $url,
        public readonly PurgeRoute $route,
        ?array $contexts = null,
    ) {
        if ([] === $contexts) {
            throw new \ValueError('The list must contain at least one context.');
        }

        $this->contexts = $contexts ?? [$route->context];
    }

    /**
     * A request serialized before `contexts` existed, e.g. in a queued purge message, has no value for it.
     *
     * @param array{url: string, route: PurgeRoute, contexts?: non-empty-list<array<array-key, mixed>>} $data
     */
    public function __unserialize(array $data): void
    {
        $this->url = $data['url'];
        $this->route = $data['route'];
        $this->contexts = $data['contexts'] ?? [$this->route->context];
    }
}
