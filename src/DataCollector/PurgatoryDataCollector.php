<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\DataCollector;

use Sofascore\PurgatoryBundle\Purger\ContextFormatter;
use Sofascore\PurgatoryBundle\Purger\PurgeRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * @internal
 *
 * @property array{purger_name: string, async_transport: ?string, purges: list<array{requests: list<PurgeRequest>, time: float, contexts?: list<string>}>, has_contexts?: bool} $data
 */
final class PurgatoryDataCollector extends DataCollector
{
    private ?int $totalRequests = null;
    private ?float $totalTime = null;

    public function __construct(
        private readonly string $purgerName,
        private readonly ?string $asyncTransport,
    ) {
        $this->initData();
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
    }

    public function getName(): string
    {
        return 'purgatory';
    }

    /**
     * @param list<PurgeRequest> $purgeRequests
     */
    public function collectPurgeRequests(array $purgeRequests, float $time): void
    {
        $contexts = [];
        foreach ($purgeRequests as $purgeRequest) {
            $contexts[] = ContextFormatter::format($purgeRequest->contexts);

            // An empty array stands for a subscription without context
            if ([] !== array_filter($purgeRequest->contexts)) {
                $this->data['has_contexts'] = true;
            }
        }

        $this->data['purges'][] = ['requests' => $purgeRequests, 'time' => $time, 'contexts' => $contexts];
    }

    /**
     * Whether any purge request carries a context, so the profiler only shows the column when one does.
     */
    public function hasContexts(): bool
    {
        // Profiles stored before contexts were collected do not have the key
        return $this->data['has_contexts'] ?? false;
    }

    public function getTotalRequests(): int
    {
        return $this->totalRequests ??= \count(array_merge(...array_column($this->data['purges'], 'requests')));
    }

    public function getTotalTime(): float
    {
        return $this->totalTime ??= array_sum(array_column($this->data['purges'], 'time'));
    }

    public function getPurgerName(): string
    {
        return $this->data['purger_name'];
    }

    public function getAsyncTransport(): ?string
    {
        return $this->data['async_transport'];
    }

    /**
     * @return list<array{requests: list<PurgeRequest>, time: float, contexts?: list<string>}>
     */
    public function getPurges(): array
    {
        return $this->data['purges'];
    }

    public function reset(): void
    {
        $this->initData();

        $this->totalRequests = null;
        $this->totalTime = null;
    }

    private function initData(): void
    {
        $this->data = [
            'purger_name' => $this->purgerName,
            'async_transport' => $this->asyncTransport,
            'purges' => [],
            'has_contexts' => false,
        ];
    }
}
