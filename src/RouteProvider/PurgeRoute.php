<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\RouteProvider;

final class PurgeRoute
{
    /**
     * @param array<string, ?scalar>  $params
     * @param array<array-key, mixed> $context the `context` of the PurgeOn this route comes from
     */
    public function __construct(
        public readonly string $name,
        public readonly array $params,
        public readonly array $context = [],
    ) {
    }

    /**
     * A route serialized before `context` existed, e.g. in a queued purge message, has no value for it.
     *
     * @param array{name: string, params: array<string, ?scalar>, context?: array<array-key, mixed>} $data
     */
    public function __unserialize(array $data): void
    {
        $this->name = $data['name'];
        $this->params = $data['params'];
        $this->context = $data['context'] ?? [];
    }
}
