<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Doctrine\DBAL;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Sofascore\PurgatoryBundle\Listener\EntityChangeListener;

/**
 * @internal
 */
final class PurgatoryDriver extends AbstractDriverMiddleware
{
    public function __construct(
        Driver $driver,
        private readonly EntityChangeListener $entityChangeListener,
    ) {
        parent::__construct($driver);
    }

    public function connect(array $params): DriverConnection
    {
        return new PurgatoryConnection(
            wrappedConnection: parent::connect($params),
            entityChangeListener: $this->entityChangeListener,
        );
    }
}
