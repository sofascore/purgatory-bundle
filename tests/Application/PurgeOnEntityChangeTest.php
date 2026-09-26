<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Application;

use Doctrine\ORM\EntityManagerInterface;
use Sofascore\PurgatoryBundle\Listener\EntityChangePurgeSwitcher;
use Sofascore\PurgatoryBundle\Listener\EntityChangePurgeSwitcherInterface;
use Sofascore\PurgatoryBundle\Test\InteractsWithPurgatory;
use Sofascore\PurgatoryBundle\Test\TestEntityChangePurgeSwitcher;
use Sofascore\PurgatoryBundle\Tests\Functional\AbstractKernelTestCase;
use Sofascore\PurgatoryBundle\Tests\Functional\EntityChangeListener\Entity\Dummy;

final class PurgeOnEntityChangeTest extends AbstractKernelTestCase
{
    use InteractsWithPurgatory;

    protected function tearDown(): void
    {
        TestEntityChangePurgeSwitcher::reset();

        parent::tearDown();
    }

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

    public function testGlobalOverrideWithoutTestOptionIsIgnoredAndReported(): void
    {
        self::initializeApplication(['test_case' => 'EntityChangeListener', 'config' => 'purge_on_entity_change_disabled.yaml']);

        self::assertInstanceOf(EntityChangePurgeSwitcher::class, self::getSwitcher());

        TestEntityChangePurgeSwitcher::enable();

        self::assertFalse(self::getSwitcher()->isEnabled());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(\sprintf('The global override of "%s", e.g. by the "#[WithEntityChangePurging]" attribute, has no effect unless the "test" option is enabled.', TestEntityChangePurgeSwitcher::class));

        self::assertNoUrlsArePurged();
    }

    public function testUrlsArePurgedWhenEnabledGloballyWithTestOption(): void
    {
        self::initializeApplication(['test_case' => 'EntityChangeListener', 'config' => 'test_mode.yaml']);

        self::assertInstanceOf(TestEntityChangePurgeSwitcher::class, self::getSwitcher());

        TestEntityChangePurgeSwitcher::enable();

        $name = self::persistDummy();

        self::assertUrlIsPurged('http://localhost/'.$name);
        self::assertUrlIsPurged('http://example.test/foo');
    }

    public function testConfiguredDefaultIsRestoredAfterReset(): void
    {
        self::initializeApplication(['test_case' => 'EntityChangeListener', 'config' => 'test_mode.yaml']);

        TestEntityChangePurgeSwitcher::enable();
        TestEntityChangePurgeSwitcher::reset();

        self::persistDummy();

        self::assertNoUrlsArePurged();
    }

    public function testGlobalOverrideAppliesToEveryKernel(): void
    {
        TestEntityChangePurgeSwitcher::enable();

        self::initializeApplication(['test_case' => 'EntityChangeListener', 'config' => 'test_mode.yaml']);

        self::assertTrue(self::getSwitcher()->isEnabled());

        // a new kernel gets a new container and a new switcher instance
        self::ensureKernelShutdown();
        self::initializeApplication(['test_case' => 'EntityChangeListener', 'config' => 'test_mode.yaml']);

        self::assertTrue(self::getSwitcher()->isEnabled());
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
