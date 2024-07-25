<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Exception;

final class PurgeRequestFailedException extends RuntimeException
{
    /**
     * @param list<string>     $urls
     * @param list<\Throwable> $exceptions
     */
    public function __construct(
        public readonly array $urls,
        public readonly array $exceptions = [],
    ) {
        $count = \count($this->urls);
        parent::__construct(\sprintf('An error occurred while trying to purge %d URL%s.', $count, $count > 1 ? 's' : ''));
    }
}
