<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Purger\Messenger;

use Sofascore\PurgatoryBundle\Purger\PurgeRequest;

final class PurgeMessage
{
    /**
     * @param list<PurgeRequest> $purgeRequests
     */
    public function __construct(
        public readonly array $purgeRequests,
    ) {
        if (!$this->purgeRequests) {
            throw new \ValueError('The list must contain at least one URL.');
        }
    }
}
