<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Test;

use PHPUnit\Framework\Attributes\After;
use Sofascore\PurgatoryBundle2\Purger\AsyncPurger;
use Sofascore\PurgatoryBundle2\Purger\InMemoryPurger;
use Sofascore\PurgatoryBundle2\Purger\PurgerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

trait InteractsWithPurgatory
{
    private ?InMemoryPurger $purger = null;

    /**
     * @internal
     *
     * @after
     */
    #[After]
    final protected function _cleanUp(): void
    {
        $this->purger?->reset();
        $this->purger = null;
    }

    final protected function assertUrlIsPurged(string $url): void
    {
        self::assertContains(
            needle: $url,
            haystack: $this->_getPurgedUrls($url),
            message: sprintf('Failed asserting that the URL "%s" was purged.', $url),
        );
    }

    final protected function assertUrlIsNotPurged(string $url): void
    {
        self::assertNotContains(
            needle: $url,
            haystack: $this->_getPurgedUrls($url),
            message: sprintf('Failed asserting that the URL "%s" was not purged.', $url),
        );
    }

    final protected function assertNoUrlsArePurged(): void
    {
        self::assertEmpty(
            actual: $this->getPurger()->getPurgedUrls(),
            message: 'Failed asserting that no URLs were purged.',
        );
    }

    final protected function getPurger(): InMemoryPurger
    {
        if (null !== $this->purger) {
            return $this->purger;
        }

        if (!$this instanceof KernelTestCase) {
            throw new \LogicException(sprintf('The "%s" trait can only be used with "%s".', __TRAIT__, KernelTestCase::class));
        }

        $purger = self::getContainer()->get(PurgerInterface::class);

        if ($purger instanceof AsyncPurger) {
            $purger = self::getContainer()->get('sofascore.purgatory2.purger.sync');
        }

        if (!$purger instanceof InMemoryPurger) {
            throw new \LogicException(sprintf('The "%s" trait can only be used if "InMemoryPurger" is set as the purger.', __TRAIT__));
        }

        return $this->purger = $purger;
    }

    final protected function clearPurger(): void
    {
        $this->getPurger()->reset();
    }

    private function _getPurgedUrls(string $url): array
    {
        $purgedUrls = $this->getPurger()->getPurgedUrls();

        if (str_contains($url, '://')) {
            return $purgedUrls;
        }

        return array_map(static function (string $url): string {
            $parsedUrl = parse_url($url);

            return ($parsedUrl['path'] ?? '/').(isset($parsedUrl['query']) ? '?'.$parsedUrl['query'] : '');
        }, $purgedUrls);
    }
}
