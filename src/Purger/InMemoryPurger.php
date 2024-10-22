<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Purger;

final class InMemoryPurger implements PurgerInterface
{
    /** @var list<PurgeRequest> */
    private array $purgedRequests = [];

    /**
     * {@inheritDoc}
     */
    public function purge(iterable $purgeRequests): void
    {
        foreach ($purgeRequests as $purgeRequest) {
            $this->purgedRequests[] = $purgeRequest;
        }
    }

    /**
     * @return list<PurgeRequest>
     */
    public function getPurgedRequests(): array
    {
        return $this->purgedRequests;
    }

    /**
     * @return list<string>
     */
    public function getPurgedUrls(): array
    {
        return array_map(static fn (PurgeRequest $purgeRequest): string => $purgeRequest->url, $this->purgedRequests);
    }

    public function reset(): void
    {
        $this->purgedRequests = [];
    }
}
