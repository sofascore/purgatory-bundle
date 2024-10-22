<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Purger;

use Sofascore\PurgatoryBundle\Exception\InvalidArgumentException;
use Sofascore\PurgatoryBundle\Exception\PurgeRequestFailedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class VarnishPurger implements PurgerInterface
{
    /**
     * @param list<string> $hosts
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly array $hosts = [],
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function purge(iterable $purgeRequests): void
    {
        $responses = [];

        foreach ($purgeRequests as $purgeRequest) {
            if (!$this->hosts) {
                $responses[] = $this->httpClient->request(Request::METHOD_PURGE, $purgeRequest->url);
                continue;
            }

            [$urlHost, $urlPathAndQuery] = self::splitUrl($purgeRequest->url);
            foreach ($this->hosts as $host) {
                $responses[] = $this->httpClient->request(Request::METHOD_PURGE, $host.$urlPathAndQuery, [
                    'headers' => [
                        'Host' => $urlHost,
                    ],
                ]);
            }
        }

        $failedUrls = [];
        $exceptions = [];
        foreach ($responses as $response) {
            try {
                $response->getHeaders(); // trigger concurrent requests
            } catch (HttpExceptionInterface $e) {
                /** @var string $failedUrl */
                $failedUrl = $e->getResponse()->getInfo('url');
                $failedUrls[] = $failedUrl;
                $exceptions[] = $e;
            }
        }

        if ($failedUrls) {
            throw new PurgeRequestFailedException($failedUrls, $exceptions);
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function splitUrl(string $url): array
    {
        $parsedUrl = parse_url($url);

        if (!isset($parsedUrl['host'])) {
            throw new InvalidArgumentException(\sprintf('Invalid URL "%s" provided. The URL must contain a host.', $url));
        }

        return [
            $parsedUrl['host'].(isset($parsedUrl['port']) ? ':'.$parsedUrl['port'] : ''),
            ($parsedUrl['path'] ?? '/').(isset($parsedUrl['query']) ? '?'.$parsedUrl['query'] : ''),
        ];
    }
}
