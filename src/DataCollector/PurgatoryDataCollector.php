<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\DataCollector;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * @internal
 *
 * @property array{purger_name: string, async_transport: ?string, purges: list<array{urls: list<string>, time: float}>} $data
 */
final class PurgatoryDataCollector extends DataCollector
{
    private ?int $totalUrls = null;
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
     * @param list<string> $urls
     */
    public function collectPurgedUrls(array $urls, float $time): void
    {
        $this->data['purges'][] = ['urls' => $urls, 'time' => $time];
    }

    public function getTotalUrls(): int
    {
        return $this->totalUrls ??= \count(array_merge(...array_column($this->data['purges'], 'urls')));
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
     * @return list<array{urls: list<string>, time: float}>
     */
    public function getPurges(): array
    {
        return $this->data['purges'];
    }

    public function reset(): void
    {
        $this->initData();

        $this->totalUrls = null;
        $this->totalTime = null;
    }

    private function initData(): void
    {
        $this->data = [
            'purger_name' => $this->purgerName,
            'async_transport' => $this->asyncTransport,
            'purges' => [],
        ];
    }
}
