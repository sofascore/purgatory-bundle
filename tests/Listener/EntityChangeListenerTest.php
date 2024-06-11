<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Listener;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Sofascore\PurgatoryBundle2\Listener\EntityChangeListener;
use Sofascore\PurgatoryBundle2\Tests\Functional\AbstractKernelTestCase;
use Sofascore\PurgatoryBundle2\Tests\Functional\EntityChangeListener\Entity\TestEntity;

#[CoversClass(EntityChangeListener::class)]
final class EntityChangeListenerTest extends AbstractKernelTestCase
{
    public function testExpectedUrlsArePurged(): void
    {
        self::initializeApplication(['test_case' => 'EntityChangeListener']);

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');

        $test = new TestEntity($name = 'name_'.time());

        $em->persist($test);
        $em->flush();

        $em->remove($test);
        $em->flush();

        self::assertSame(
            ['/'.$name, '/'.$name],
            self::getContainer()->get('sofascore.purgatory.purger.in_memory')->getPurgedUrls(),
        );
    }
}
