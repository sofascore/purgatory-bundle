<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Listener;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Sofascore\PurgatoryBundle\Listener\EntityChangeListener;
use Sofascore\PurgatoryBundle\Purger\PurgerInterface;
use Sofascore\PurgatoryBundle\Test\InteractsWithPurgatory;
use Sofascore\PurgatoryBundle\Tests\Functional\AbstractKernelTestCase;
use Sofascore\PurgatoryBundle\Tests\Functional\EntityChangeListener\Entity\Dummy;
use Sofascore\PurgatoryBundle\Tests\Functional\EntityChangeListener\Entity\DummyParent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[CoversClass(EntityChangeListener::class)]
final class EntityChangeListenerTest extends AbstractKernelTestCase
{
    use InteractsWithPurgatory;

    public function testExpectedUrlsArePurged(): void
    {
        self::initializeApplication(['test_case' => 'EntityChangeListener']);

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');

        $test = new Dummy($name = 'name_'.time());

        $em->persist($test);
        $em->flush();

        self::assertUrlIsPurged('http://localhost/'.$name);
        self::assertUrlIsPurged('http://example.test/foo');
        self::clearPurger();

        $em->remove($test);
        $em->flush();

        self::assertUrlIsPurged('http://localhost/'.$name);
        self::assertUrlIsPurged('http://example.test/foo');
    }

    public function testDuplicateUrlsAreNotPurged(): void
    {
        self::initializeApplication(['test_case' => 'EntityChangeListener']);

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');

        $test1 = new Dummy($name = 'name_'.time());
        $test2 = new DummyParent($test1);

        $em->persist($test1);
        $em->persist($test2);
        $em->flush();

        self::assertCount(2, self::getPurger()->getPurgedRequests());
        self::assertUrlIsPurged('http://localhost/'.$name);
        self::assertUrlIsPurged('http://example.test/foo');
    }

    public function testUrlsAreNotPurgedOnFlushWhenInTransaction(): void
    {
        self::initializeApplication(['test_case' => 'EntityChangeListener', 'config' => 'no_middleware.yaml']);

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');

        $test = new Dummy($name = 'name_'.time());

        $em->persist($test);

        $em->wrapInTransaction(static function () use ($em) {
            $em->flush();
        });

        self::assertNoUrlsArePurged();

        $em->flush();

        self::assertUrlIsPurged('http://localhost/'.$name);
        self::assertUrlIsPurged('http://example.test/foo');
    }

    public function testQueuedPurgeRequestsAreClearedOnKernelReset(): void
    {
        self::initializeApplication(['test_case' => 'EntityChangeListener', 'config' => 'no_middleware.yaml']);

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');

        $em->persist(new Dummy($name = 'name_'.time()));

        $em->wrapInTransaction(static function () use ($em) {
            $em->flush();
        });

        /** @var EntityChangeListener $entityChangeListener */
        $entityChangeListener = self::getContainer()->get('sofascore.purgatory.entity_change_listener');
        $queuedPurgeRequestsReflection = new \ReflectionProperty(EntityChangeListener::class, 'queuedPurgeRequests');

        self::assertSame(
            ['http://localhost/'.$name, 'http://example.test/foo'],
            array_keys($queuedPurgeRequestsReflection->getValue($entityChangeListener)),
        );

        self::getContainer()->get('services_resetter')->reset();

        self::assertSame([], $queuedPurgeRequestsReflection->getValue($entityChangeListener));

        $entityChangeListener->process();

        self::assertNoUrlsArePurged();
    }

    public function testProcessWithNoPurgeRequests(): void
    {
        $urlGenerator = self::createStub(UrlGeneratorInterface::class);
        $purger = $this->createMock(PurgerInterface::class);
        $purger->expects(self::never())->method('purge');

        $entityChangeListener = new EntityChangeListener([], $urlGenerator, $purger);

        $entityChangeListener->process();
    }
}
