<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Listener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\Attributes\CoversClass;
use Sofascore\PurgatoryBundle\Listener\EntityChangeListener;
use Sofascore\PurgatoryBundle\Listener\EntityChangePurgeSwitcher;
use Sofascore\PurgatoryBundle\Purger\PurgerInterface;
use Sofascore\PurgatoryBundle\RouteProvider\PurgeRoute;
use Sofascore\PurgatoryBundle\RouteProvider\RouteProviderInterface;
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

    public function testProcessWithNoPurgeRequests(): void
    {
        $urlGenerator = self::createStub(UrlGeneratorInterface::class);
        $purger = $this->createMock(PurgerInterface::class);
        $purger->expects(self::never())->method('purge');

        $entityChangeListener = new EntityChangeListener([], $urlGenerator, $purger, new EntityChangePurgeSwitcher());

        $entityChangeListener->process();
    }

    public function testNothingIsQueuedWhenPurgingIsDisabled(): void
    {
        $routeProvider = $this->createMock(RouteProviderInterface::class);
        $routeProvider->expects(self::never())->method('supports');
        $purger = $this->createMock(PurgerInterface::class);
        $purger->expects(self::never())->method('purge');
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('getUnitOfWork');

        $entityChangeListener = new EntityChangeListener(
            [$routeProvider],
            self::createStub(UrlGeneratorInterface::class),
            $purger,
            new EntityChangePurgeSwitcher(false),
        );

        $entityChangeListener->postPersist(new PostPersistEventArgs(new \stdClass(), $entityManager));
        $entityChangeListener->process();
    }

    public function testQueuedPurgeRequestsAreDroppedWhenPurgingIsDisabledBeforeProcessing(): void
    {
        $routeProvider = self::createStub(RouteProviderInterface::class);
        $routeProvider->method('supports')->willReturn(true);
        $routeProvider->method('provideRoutesFor')->willReturn([new PurgeRoute('route_foo', [])]);
        $urlGenerator = self::createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('http://localhost/foo');
        $purger = $this->createMock(PurgerInterface::class);
        $purger->expects(self::never())->method('purge');
        $unitOfWork = self::createStub(UnitOfWork::class);
        $unitOfWork->method('getEntityChangeSet')->willReturn([]);
        $entityManager = self::createStub(EntityManagerInterface::class);
        $entityManager->method('getUnitOfWork')->willReturn($unitOfWork);

        $switcher = new EntityChangePurgeSwitcher(true);
        $entityChangeListener = new EntityChangeListener(
            [$routeProvider],
            $urlGenerator,
            $purger,
            $switcher,
        );

        $entityChangeListener->postPersist(new PostPersistEventArgs(new \stdClass(), $entityManager));

        $switcher->whileDisabled(static fn () => $entityChangeListener->process());

        $entityChangeListener->process();
    }
}
