<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Doctrine\DBAL;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Sofascore\PurgatoryBundle2\Doctrine\DBAL\Middleware;
use Sofascore\PurgatoryBundle2\Doctrine\DBAL\PurgatoryConnection;
use Sofascore\PurgatoryBundle2\Doctrine\DBAL\PurgatoryDriver;
use Sofascore\PurgatoryBundle2\Listener\EntityChangeListener;
use Sofascore\PurgatoryBundle2\Purger\InMemoryPurger;
use Sofascore\PurgatoryBundle2\Tests\Functional\AbstractKernelTestCase;
use Sofascore\PurgatoryBundle2\Tests\Functional\EntityChangeListener\Entity\Dummy;

#[CoversClass(Middleware::class)]
#[CoversClass(PurgatoryConnection::class)]
#[CoversClass(PurgatoryDriver::class)]
final class PurgatoryDoctrineTest extends AbstractKernelTestCase
{
    private EntityManagerInterface $entityManager;
    private InMemoryPurger $purger;
    private EntityChangeListener $entityChangeListener;

    protected function setUp(): void
    {
        self::initializeApplication(['test_case' => 'EntityChangeListener']);

        $this->entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $this->purger = self::getContainer()->get('sofascore.purgatory2.purger.in_memory');
        $this->entityChangeListener = self::getContainer()->get('sofascore.purgatory2.entity_change_listener');
    }

    protected function tearDown(): void
    {
        unset(
            $this->entityManager,
            $this->purger,
            $this->entityChangeListener,
        );
        parent::tearDown();
    }

    public function testUrlsArePurgedAfterWrapInTransaction(): void
    {
        $name = 'name_'.time();

        $this->entityManager->wrapInTransaction(
            function () use ($name) {
                self::assertSame([], $this->purger->getPurgedUrls());

                $test = new Dummy($name);

                $this->entityManager->persist($test);
                $this->entityManager->flush();

                self::assertSame(['/'.$name => true], $this->getQueuedUrls());
                self::assertSame([], $this->purger->getPurgedUrls());

                $this->entityManager->remove($test);
                $this->entityManager->flush();

                self::assertSame(['/'.$name => true], $this->getQueuedUrls());
                self::assertSame([], $this->purger->getPurgedUrls());
            },
        );

        self::assertSame([], $this->getQueuedUrls());
        self::assertSame(['/'.$name], $this->purger->getPurgedUrls());
    }

    public function testUrlsArePurgedAfterExplicitTransactionCommit(): void
    {
        $name = 'name_'.time();

        $this->entityManager->getConnection()->beginTransaction();

        self::assertSame([], $this->purger->getPurgedUrls());

        $test = new Dummy($name);

        $this->entityManager->persist($test);
        $this->entityManager->flush();

        self::assertSame(['/'.$name => true], $this->getQueuedUrls());
        self::assertSame([], $this->purger->getPurgedUrls());

        $this->entityManager->remove($test);
        $this->entityManager->flush();

        self::assertSame(['/'.$name => true], $this->getQueuedUrls());
        self::assertSame([], $this->purger->getPurgedUrls());

        $this->entityManager->getConnection()->commit();

        self::assertSame([], $this->getQueuedUrls());
        self::assertSame(['/'.$name], $this->purger->getPurgedUrls());
    }

    public function testRollbackTransaction(): void
    {
        $name = 'name_'.time();

        $this->entityManager->getConnection()->beginTransaction();

        self::assertSame([], $this->purger->getPurgedUrls());

        $test = new Dummy($name);

        $this->entityManager->persist($test);
        $this->entityManager->flush();

        self::assertSame(['/'.$name => true], $this->getQueuedUrls());
        self::assertSame([], $this->purger->getPurgedUrls());

        $this->entityManager->remove($test);
        $this->entityManager->flush();

        self::assertSame(['/'.$name => true], $this->getQueuedUrls());
        self::assertSame([], $this->purger->getPurgedUrls());

        $this->entityManager->getConnection()->rollBack();

        self::assertSame([], $this->getQueuedUrls());
        self::assertSame([], $this->purger->getPurgedUrls());
    }

    /**
     * @return array<string, true>
     */
    private function getQueuedUrls(): array
    {
        return \Closure::bind(fn (): array => $this->queuedUrls, $this->entityChangeListener, EntityChangeListener::class)();
    }
}
