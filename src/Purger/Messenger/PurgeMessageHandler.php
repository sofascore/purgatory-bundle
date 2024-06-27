<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Purger\Messenger;

use Sofascore\PurgatoryBundle2\Purger\PurgerInterface;

final class PurgeMessageHandler
{
    public function __construct(
        private readonly PurgerInterface $purger,
    ) {
    }

    public function __invoke(PurgeMessage $message): void
    {
        $this->purger->purge($message->urls);
    }
}
