<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Doctrine\DBAL;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Sofascore\PurgatoryBundle\Doctrine\DBAL\Middleware;
use Sofascore\PurgatoryBundle\Doctrine\DBAL\PurgatoryConnection;
use Sofascore\PurgatoryBundle\Doctrine\DBAL\PurgatoryDriver;
use Sofascore\PurgatoryBundle\Listener\EntityChangeListener;
use Sofascore\PurgatoryBundle\Purger\PurgeRequest;
use Sofascore\PurgatoryBundle\Test\InteractsWithPurgatory;
use Sofascore\PurgatoryBundle\Tests\Functional\AbstractKernelTestCase;
use Sofascore\PurgatoryBundle\Tests\Functional\EntityChangeListener\Entity\Dummy;

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
        $this->entityChangeListener = self::getContainer()->get('sofascore.purgatory.entity_change_listener');
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
                self::assertNoUrlsArePurged();

                $test = new Dummy($name);

                $this->entityManager->persist($test);
                $this->entityManager->flush();

                self::assertSame(['http://localhost/'.$name, 'http://example.test/foo'], $this->getQueuedUrls());
                self::assertNoUrlsArePurged();

                $this->entityManager->remove($test);
                $this->entityManager->flush();

                self::assertSame(['http://localhost/'.$name, 'http://example.test/foo'], $this->getQueuedUrls());
                self::assertNoUrlsArePurged();
            },
        );

        self::assertSame([], $this->getQueuedUrls());
        self::assertUrlIsPurged('/'.$name);
        self::assertUrlIsPurged('http://example.test/foo');
    }

    public function testUrlsArePurgedAfterExplicitTransactionCommit(): void
    {
        $name = 'name_'.time();

        $this->entityManager->getConnection()->beginTransaction();

        self::assertNoUrlsArePurged();

        $test = new Dummy($name);

        $this->entityManager->persist($test);
        $this->entityManager->flush();

        self::assertSame(['http://localhost/'.$name, 'http://example.test/foo'], $this->getQueuedUrls());
        self::assertNoUrlsArePurged();

        $this->entityManager->remove($test);
        $this->entityManager->flush();

        self::assertSame(['http://localhost/'.$name, 'http://example.test/foo'], $this->getQueuedUrls());
        self::assertNoUrlsArePurged();

        $this->entityManager->getConnection()->commit();

        self::assertSame([], $this->getQueuedUrls());
        self::assertUrlIsPurged('/'.$name);
        self::assertUrlIsPurged('http://example.test/foo');
    }

    public function testRollbackTransaction(): void
    {
        $name = 'name_'.time();

        $this->entityManager->getConnection()->beginTransaction();

        self::assertNoUrlsArePurged();

        $test = new Dummy($name);

        $this->entityManager->persist($test);
        $this->entityManager->flush();

        self::assertSame(['http://localhost/'.$name, 'http://example.test/foo'], $this->getQueuedUrls());
        self::assertNoUrlsArePurged();

        $this->entityManager->remove($test);
        $this->entityManager->flush();

        self::assertSame(['http://localhost/'.$name, 'http://example.test/foo'], $this->getQueuedUrls());
        self::assertNoUrlsArePurged();

        $this->entityManager->getConnection()->rollBack();

        self::assertSame([], $this->getQueuedUrls());
        self::assertNoUrlsArePurged();
    }

    /**
     * @return array<string, true>
     */
    private function getQueuedUrls(): array
    {
        $purgeRequests = \Closure::bind(fn (): array => $this->queuedPurgeRequests, $this->entityChangeListener, EntityChangeListener::class)();

        return array_values(array_map(static fn (PurgeRequest $purgeRequest) => $purgeRequest->url, $purgeRequests));
    }
}
