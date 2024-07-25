<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Purger\Messenger;

final class PurgeMessage
{
    /**
     * @param list<string> $urls
     */
    public function __construct(
        public readonly array $urls,
    ) {
        if (!$this->urls) {
            throw new \ValueError('The list must contain at least one URL.');
        }
    }
}
