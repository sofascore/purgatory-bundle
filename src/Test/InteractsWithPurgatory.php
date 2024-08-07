<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Test;

use PHPUnit\Framework\Attributes\After;
use Sofascore\PurgatoryBundle\Purger\AsyncPurger;
use Sofascore\PurgatoryBundle\Purger\InMemoryPurger;
use Sofascore\PurgatoryBundle\Purger\PurgerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

trait InteractsWithPurgatory
{
    /**
     * @internal
     */
    private ?InMemoryPurger $_purger = null;

    /**
     * @internal
     *
     * @after
     */
    #[After]
    final protected function _cleanUp(): void
    {
        $this->_purger?->reset();
        $this->_purger = null;
    }

    final protected function assertUrlIsPurged(string $url): void
    {
        self::assertContains(
            needle: $url,
            haystack: $this->getPurgedUrls(str_contains($url, '://')),
            message: \sprintf('Failed asserting that the URL "%s" was purged.', $url),
        );
    }

    final protected function assertUrlIsNotPurged(string $url): void
    {
        self::assertNotContains(
            needle: $url,
            haystack: $this->getPurgedUrls(str_contains($url, '://')),
            message: \sprintf('Failed asserting that the URL "%s" was not purged.', $url),
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
        if (null !== $this->_purger) {
            return $this->_purger;
        }

        if (!$this instanceof KernelTestCase) {
            throw new \LogicException(\sprintf('The "%s" trait can only be used with "%s".', __TRAIT__, KernelTestCase::class));
        }

        $purger = self::getContainer()->get(PurgerInterface::class);

        if ($purger instanceof AsyncPurger) {
            $purger = self::getContainer()->get('sofascore.purgatory.purger.sync');
        }

        if (!$purger instanceof InMemoryPurger) {
            throw new \LogicException(\sprintf('The "%s" trait can only be used if "InMemoryPurger" is set as the purger.', __TRAIT__));
        }

        return $this->_purger = $purger;
    }

    final protected function clearPurger(): void
    {
        $this->getPurger()->reset();
    }

    final protected function getPurgedUrls(bool $absoluteUrls): array
    {
        $purgedUrls = $this->getPurger()->getPurgedUrls();

        if ($absoluteUrls) {
            return $purgedUrls;
        }

        return array_map(static function (string $url): string {
            $parsedUrl = parse_url($url);

            return ($parsedUrl['path'] ?? '/').(isset($parsedUrl['query']) ? '?'.$parsedUrl['query'] : '');
        }, $purgedUrls);
    }
}
