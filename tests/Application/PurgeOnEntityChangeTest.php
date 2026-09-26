<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Application;

use Doctrine\ORM\EntityManagerInterface;
use Sofascore\PurgatoryBundle\Listener\EntityChangePurgeSwitcherInterface;
use Sofascore\PurgatoryBundle\Test\InteractsWithPurgatory;
use Sofascore\PurgatoryBundle\Tests\Functional\AbstractKernelTestCase;
use Sofascore\PurgatoryBundle\Tests\Functional\EntityChangeListener\Entity\Dummy;

final class PurgeOnEntityChangeTest extends AbstractKernelTestCase
{
    use InteractsWithPurgatory;

    public function testUrlsAreNotPurgedWhenDisabled(): void
    {
        self::initializeApplication(['test_case' => 'EntityChangeListener', 'config' => 'purge_on_entity_change_disabled.yaml']);

        self::persistDummy();

        self::assertNoUrlsArePurged();
    }

    public function testUrlsArePurgedWhileEnabled(): void
    {
        self::initializeApplication(['test_case' => 'EntityChangeListener', 'config' => 'purge_on_entity_change_disabled.yaml']);

        $name = self::getSwitcher()->whileEnabled(static fn (): string => self::persistDummy());

        self::assertUrlIsPurged('http://localhost/'.$name);
        self::assertUrlIsPurged('http://example.test/foo');
    }

    private static function getSwitcher(): EntityChangePurgeSwitcherInterface
    {
        return self::getContainer()->get(EntityChangePurgeSwitcherInterface::class);
    }

    private static function persistDummy(): string
    {
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');

        $em->persist(new Dummy($name = 'name_'.time()));
        $em->flush();

        return $name;
    }
}
