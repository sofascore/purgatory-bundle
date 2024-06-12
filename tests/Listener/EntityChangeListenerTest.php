<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Listener;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Sofascore\PurgatoryBundle2\Listener\EntityChangeListener;
use Sofascore\PurgatoryBundle2\Purger\InMemoryPurger;
use Sofascore\PurgatoryBundle2\Tests\Functional\AbstractKernelTestCase;
use Sofascore\PurgatoryBundle2\Tests\Functional\EntityChangeListener\Entity\Dummy;

#[CoversClass(EntityChangeListener::class)]
final class EntityChangeListenerTest extends AbstractKernelTestCase
{
    public function testExpectedUrlsArePurged(): void
    {
        self::initializeApplication(['test_case' => 'EntityChangeListener']);

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');

        /** @var InMemoryPurger $purger */
        $purger = self::getContainer()->get('sofascore.purgatory.purger.in_memory');

        $test = new Dummy($name = 'name_'.time());

        $em->persist($test);
        $em->flush();

        self::assertSame(
            ['/'.$name],
            $purger->getPurgedUrls(),
        );

        $em->remove($test);
        $em->flush();

        self::assertSame(
            ['/'.$name, '/'.$name],
            $purger->getPurgedUrls(),
        );
    }
}
