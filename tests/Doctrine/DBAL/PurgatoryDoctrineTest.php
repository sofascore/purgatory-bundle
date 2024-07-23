<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Doctrine\DBAL;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Sofascore\PurgatoryBundle2\Doctrine\DBAL\Middleware;
use Sofascore\PurgatoryBundle2\Doctrine\DBAL\PurgatoryConnection;
use Sofascore\PurgatoryBundle2\Doctrine\DBAL\PurgatoryDriver;
use Sofascore\PurgatoryBundle2\Listener\EntityChangeListener;
use Sofascore\PurgatoryBundle2\Test\InteractsWithPurgatory;
use Sofascore\PurgatoryBundle2\Tests\Functional\AbstractKernelTestCase;
use Sofascore\PurgatoryBundle2\Tests\Functional\EntityChangeListener\Entity\Dummy;

#[CoversClass(Middleware::class)]
#[CoversClass(PurgatoryConnection::class)]
#[CoversClass(PurgatoryDriver::class)]
final class PurgatoryDoctrineTest extends AbstractKernelTestCase
{
    use InteractsWithPurgatory;

    private EntityManagerInterface $entityManager;
    private EntityChangeListener $entityChangeListener;

    protected function setUp(): void
    {
        self::initializeApplication(['test_case' => 'EntityChangeListener']);

        $this->entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $this->entityChangeListener = self::getContainer()->get('sofascore.purgatory2.entity_change_listener');
    }

    protected function tearDown(): void
    {
        unset(
            $this->entityManager,
            $this->entityChangeListener,
        );
        parent::tearDown();
    }

    public function testUrlsArePurgedAfterWrapInTransaction(): void
    {
        $name = 'name_'.time();

        $this->entityManager->wrapInTransaction(
            function () use ($name) {
                $this->assertNoUrlsWerePurged();

                $test = new Dummy($name);

                $this->entityManager->persist($test);
                $this->entityManager->flush();

                self::assertSame(['/'.$name => true], $this->getQueuedUrls());
                $this->assertNoUrlsWerePurged();

                $this->entityManager->remove($test);
                $this->entityManager->flush();

                self::assertSame(['/'.$name => true], $this->getQueuedUrls());
                $this->assertNoUrlsWerePurged();
            },
        );

        self::assertSame([], $this->getQueuedUrls());
        $this->assertUrlIsPurged('/'.$name);
    }

    public function testUrlsArePurgedAfterExplicitTransactionCommit(): void
    {
        $name = 'name_'.time();

        $this->entityManager->getConnection()->beginTransaction();

        $this->assertNoUrlsWerePurged();

        $test = new Dummy($name);

        $this->entityManager->persist($test);
        $this->entityManager->flush();

        self::assertSame(['/'.$name => true], $this->getQueuedUrls());
        $this->assertNoUrlsWerePurged();

        $this->entityManager->remove($test);
        $this->entityManager->flush();

        self::assertSame(['/'.$name => true], $this->getQueuedUrls());
        $this->assertNoUrlsWerePurged();

        $this->entityManager->getConnection()->commit();

        self::assertSame([], $this->getQueuedUrls());
        $this->assertUrlIsPurged('/'.$name);
    }

    public function testRollbackTransaction(): void
    {
        $name = 'name_'.time();

        $this->entityManager->getConnection()->beginTransaction();

        $this->assertNoUrlsWerePurged();

        $test = new Dummy($name);

        $this->entityManager->persist($test);
        $this->entityManager->flush();

        self::assertSame(['/'.$name => true], $this->getQueuedUrls());
        $this->assertNoUrlsWerePurged();

        $this->entityManager->remove($test);
        $this->entityManager->flush();

        self::assertSame(['/'.$name => true], $this->getQueuedUrls());
        $this->assertNoUrlsWerePurged();

        $this->entityManager->getConnection()->rollBack();

        self::assertSame([], $this->getQueuedUrls());
        $this->assertNoUrlsWerePurged();
    }

    /**
     * @return array<string, true>
     */
    private function getQueuedUrls(): array
    {
        return \Closure::bind(fn (): array => $this->queuedUrls, $this->entityChangeListener, EntityChangeListener::class)();
    }
}
