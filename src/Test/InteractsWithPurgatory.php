<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Test;

use Sofascore\PurgatoryBundle\Purger\AsyncPurger;
use Sofascore\PurgatoryBundle\Purger\InMemoryPurger;
use Sofascore\PurgatoryBundle\Purger\PurgerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

trait InteractsWithPurgatory
{
    final protected static function assertUrlIsPurged(string $url): void
    {
        self::assertContains(
            needle: $url,
            haystack: self::getPurgedUrls(str_contains($url, '://')),
            message: \sprintf('Failed asserting that the URL "%s" was purged.', $url),
        );
    }

    final protected static function assertUrlIsNotPurged(string $url): void
    {
        self::assertNotContains(
            needle: $url,
            haystack: self::getPurgedUrls(str_contains($url, '://')),
            message: \sprintf('Failed asserting that the URL "%s" was not purged.', $url),
        );
    }

    final protected static function assertNoUrlsArePurged(): void
    {
        self::assertEmpty(
            actual: self::getPurger()->getPurgedUrls(),
            message: 'Failed asserting that no URLs were purged.',
        );
    }

    final protected static function getPurger(): InMemoryPurger
    {
        if (!is_a(static::class, KernelTestCase::class, true)) {
            throw new \LogicException(\sprintf('The "%s" trait can only be used with "%s".', __TRAIT__, KernelTestCase::class));
        }

        $purger = static::getContainer()->get(PurgerInterface::class);

        if ($purger instanceof AsyncPurger) {
            $purger = static::getContainer()->get('sofascore.purgatory.purger.sync');
        }

        if (!$purger instanceof InMemoryPurger) {
            throw new \LogicException(\sprintf('The "%s" trait can only be used if "InMemoryPurger" is set as the purger.', __TRAIT__));
        }

        return $purger;
    }

    final protected static function clearPurger(): void
    {
        self::getPurger()->reset();
    }

    final protected static function getPurgedUrls(bool $absoluteUrls): array
    {
        $purgedUrls = self::getPurger()->getPurgedUrls();

        if ($absoluteUrls) {
            return $purgedUrls;
        }

        return array_map(static function (string $url): string {
            $parsedUrl = parse_url($url);

            return ($parsedUrl['path'] ?? '/').(isset($parsedUrl['query']) ? '?'.$parsedUrl['query'] : '');
        }, $purgedUrls);
    }
}
