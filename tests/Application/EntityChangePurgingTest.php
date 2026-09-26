<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Application;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Depends;
use Sofascore\PurgatoryBundle\Test\InteractsWithPurgatory;
use Sofascore\PurgatoryBundle\Test\PHPUnit\WithEntityChangePurging;
use Sofascore\PurgatoryBundle\Test\TestEntityChangePurgeSwitcher;
use Sofascore\PurgatoryBundle\Tests\Functional\AbstractKernelTestCase;
use Sofascore\PurgatoryBundle\Tests\Functional\EntityChangeListener\Entity\Dummy;

final class EntityChangePurgingTest extends AbstractKernelTestCase
{
    use InteractsWithPurgatory;

    private const EXPECTED_STATE = [
        'testUrlsAreNotPurgedWhenDisabled' => false,
        'testUrlsArePurgedWhenEnabledForTest' => true,
        'testConfiguredDefaultIsRestoredAfterTest' => false,
    ];

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        // the extension must have applied the attribute before the test is prepared
        self::assertSame(self::EXPECTED_STATE[$this->name()], (new TestEntityChangePurgeSwitcher(false))->isEnabled());

        self::initializeApplication(['test_case' => 'EntityChangeListener', 'config' => 'test_mode.yaml']);

        $this->entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
    }

    protected function tearDown(): void
    {
        // the attribute must still apply while the test is being torn down
        self::assertSame(self::EXPECTED_STATE[$this->name()], (new TestEntityChangePurgeSwitcher(false))->isEnabled());

        unset($this->entityManager);

        parent::tearDown();
    }

    public function testUrlsAreNotPurgedWhenDisabled(): void
    {
        $this->persistDummy();

        self::assertNoUrlsArePurged();
    }

    #[WithEntityChangePurging]
    public function testUrlsArePurgedWhenEnabledForTest(): void
    {
        $name = $this->persistDummy();

        self::assertUrlIsPurged('http://localhost/'.$name);
        self::assertUrlIsPurged('http://example.test/foo');
    }

    #[Depends('testUrlsArePurgedWhenEnabledForTest')]
    public function testConfiguredDefaultIsRestoredAfterTest(): void
    {
        $this->persistDummy();

        self::assertNoUrlsArePurged();
    }

    private function persistDummy(): string
    {
        $dummy = new Dummy($name = 'name_'.time());

        $this->entityManager->persist($dummy);
        $this->entityManager->flush();

        return $name;
    }
}
