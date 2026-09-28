<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Application;

use Doctrine\ORM\EntityManagerInterface;
use Sofascore\PurgatoryBundle\Test\InteractsWithPurgatory;
use Sofascore\PurgatoryBundle\Test\PHPUnit\WithEntityChangePurging;
use Sofascore\PurgatoryBundle\Test\TestEntityChangePurgeSwitcher;
use Sofascore\PurgatoryBundle\Tests\Functional\AbstractKernelTestCase;
use Sofascore\PurgatoryBundle\Tests\Functional\EntityChangeListener\Entity\Dummy;

#[WithEntityChangePurging]
final class EntityChangePurgingForClassTest extends AbstractKernelTestCase
{
    use InteractsWithPurgatory;

    private EntityManagerInterface $entityManager;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // purging is enabled for the whole class, before the first test starts
        self::assertTrue((new TestEntityChangePurgeSwitcher(false))->isEnabled());
    }

    public static function tearDownAfterClass(): void
    {
        // purging is still enabled after the last test of the class has run
        self::assertTrue((new TestEntityChangePurgeSwitcher(false))->isEnabled());

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        self::initializeApplication(['test_case' => 'EntityChangeListener', 'config' => 'test_mode.yaml']);

        $this->entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
    }

    protected function tearDown(): void
    {
        unset($this->entityManager);

        parent::tearDown();
    }

    public function testUrlsArePurged(): void
    {
        $name = $this->persistDummy();

        self::assertUrlIsPurged('http://localhost/'.$name);
        self::assertUrlIsPurged('http://example.test/foo');
    }

    public function testUrlsArePurgedForEveryTest(): void
    {
        $name = $this->persistDummy();

        self::assertUrlIsPurged('http://localhost/'.$name);
        self::assertUrlIsPurged('http://example.test/foo');
    }

    private function persistDummy(): string
    {
        $dummy = new Dummy($name = 'name_'.time());

        $this->entityManager->persist($dummy);
        $this->entityManager->flush();

        return $name;
    }
}
