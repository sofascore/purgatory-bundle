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

        $this->assertUrlIsPurged('http://localhost/'.$name);
        $this->assertUrlIsPurged('http://example.test/foo');
        $this->clearPurger();

        $em->remove($test);
        $em->flush();

        $this->assertUrlIsPurged('http://localhost/'.$name);
        $this->assertUrlIsPurged('http://example.test/foo');
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

        $this->assertNoUrlsArePurged();

        $em->flush();

        $this->assertUrlIsPurged('http://localhost/'.$name);
        $this->assertUrlIsPurged('http://example.test/foo');
    }

    public function testProcessWithNoURLs(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $purger = $this->createMock(PurgerInterface::class);
        $purger->expects(self::never())->method('purge');

        $entityChangeListener = new EntityChangeListener([], $urlGenerator, $purger);

        $entityChangeListener->process();
    }
}
