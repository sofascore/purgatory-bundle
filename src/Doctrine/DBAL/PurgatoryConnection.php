<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Doctrine\DBAL;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Sofascore\PurgatoryBundle\Listener\EntityChangeListener;

if (method_exists(Connection::class, 'getEventManager')) {
    // DBAL < 4
    /**
     * @internal
     */
    final class PurgatoryConnection extends AbstractConnectionMiddleware
    {
        public function __construct(
            ConnectionInterface $wrappedConnection,
            private readonly EntityChangeListener $entityChangeListener,
        ) {
            parent::__construct($wrappedConnection);
        }

        public function commit(): bool
        {
            $result = parent::commit();

            $this->entityChangeListener->process();

            return $result;
        }

        public function rollBack(): bool
        {
            $result = parent::rollBack();

            $this->entityChangeListener->reset();

            return $result;
        }
    }
} else {
    // DBAL >= 4
    /**
     * @internal
     */
    final class PurgatoryConnection extends AbstractConnectionMiddleware
    {
        public function __construct(
            ConnectionInterface $wrappedConnection,
            private readonly EntityChangeListener $entityChangeListener,
        ) {
            parent::__construct($wrappedConnection);
        }

        public function commit(): void
        {
            parent::commit();

            $this->entityChangeListener->process();
        }

        public function rollBack(): void
        {
            parent::rollBack();

            $this->entityChangeListener->reset();
        }
    }
}
