<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Test\PHPUnit;

use PHPUnit\Event\Code\Phpt;
use PHPUnit\Event\Code\TestCollection;
use PHPUnit\Event\Code\TestDox;
use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\TestData\TestDataCollection;
use PHPUnit\Event\TestSuite\TestSuiteForTestClass;
use PHPUnit\Event\TestSuite\TestSuiteWithName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use PHPUnit\Metadata\MetadataCollection;
use Sofascore\PurgatoryBundle\Test\PHPUnit\PurgatoryExtension;
use Sofascore\PurgatoryBundle\Test\TestEntityChangePurgeSwitcher;
use Sofascore\PurgatoryBundle\Tests\Test\PHPUnit\Fixtures\WithClassAttributeDummy;
use Sofascore\PurgatoryBundle\Tests\Test\PHPUnit\Fixtures\WithMethodAttributeDummy;

#[CoversClass(PurgatoryExtension::class)]
final class PurgatoryExtensionTest extends TestCase
{
    private PurgatoryExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new PurgatoryExtension();
    }

    protected function tearDown(): void
    {
        TestEntityChangePurgeSwitcher::reset();

        unset($this->extension);
    }

    public function testClassAttributeAppliesUntilTestSuiteIsFinished(): void
    {
        $this->extension->testSuiteStarted(self::testSuite(WithClassAttributeDummy::class));

        self::assertTrue(TestEntityChangePurgeSwitcher::getGlobalOverride());

        $this->extension->testPreparationStarted(self::testMethod(WithClassAttributeDummy::class, 'testWithoutAttribute'));
        $this->extension->testFinished();

        self::assertTrue(TestEntityChangePurgeSwitcher::getGlobalOverride());

        $this->extension->testSuiteFinished(self::testSuite(WithClassAttributeDummy::class));

        self::assertNull(TestEntityChangePurgeSwitcher::getGlobalOverride());
    }

    public function testNothingIsAppliedWithoutClassAttribute(): void
    {
        $this->extension->testSuiteStarted(self::testSuite(WithMethodAttributeDummy::class));

        self::assertNull(TestEntityChangePurgeSwitcher::getGlobalOverride());
    }

    public function testTestSuitesNotForTestClassAreIgnored(): void
    {
        $this->extension->testSuiteStarted(self::testSuite(WithClassAttributeDummy::class));
        $this->extension->testSuiteStarted(new TestSuiteWithName('suite', 0, TestCollection::fromArray([])));
        $this->extension->testSuiteFinished(new TestSuiteWithName('suite', 0, TestCollection::fromArray([])));

        self::assertTrue(TestEntityChangePurgeSwitcher::getGlobalOverride());
    }

    public function testMethodAttributeAppliesUntilTestIsFinished(): void
    {
        $this->extension->testPreparationStarted(self::testMethod(WithMethodAttributeDummy::class, 'testWithAttribute'));

        self::assertTrue(TestEntityChangePurgeSwitcher::getGlobalOverride());

        $this->extension->testFinished();

        self::assertNull(TestEntityChangePurgeSwitcher::getGlobalOverride());
    }

    public function testMethodAttributeInsideClassAttributeKeepsItEnabled(): void
    {
        $this->extension->testSuiteStarted(self::testSuite(WithClassAttributeDummy::class));
        $this->extension->testPreparationStarted(self::testMethod(WithClassAttributeDummy::class, 'testWithAttribute'));
        $this->extension->testFinished();

        self::assertTrue(TestEntityChangePurgeSwitcher::getGlobalOverride());
    }

    public function testNonMethodTestsAreIgnored(): void
    {
        $this->extension->testPreparationStarted(new Phpt(__FILE__));

        self::assertNull(TestEntityChangePurgeSwitcher::getGlobalOverride());
    }

    public function testManualOverrideIsLeftAloneWithoutAttribute(): void
    {
        TestEntityChangePurgeSwitcher::disable();

        $this->extension->testSuiteStarted(self::testSuite(WithMethodAttributeDummy::class));
        $this->extension->testPreparationStarted(self::testMethod(WithMethodAttributeDummy::class, 'testWithoutAttribute'));
        $this->extension->testFinished();
        $this->extension->testSuiteFinished(self::testSuite(WithMethodAttributeDummy::class));

        self::assertFalse(TestEntityChangePurgeSwitcher::getGlobalOverride());
    }

    public function testManualOverrideIsRestoredAfterAttribute(): void
    {
        TestEntityChangePurgeSwitcher::disable();

        $this->extension->testPreparationStarted(self::testMethod(WithMethodAttributeDummy::class, 'testWithAttribute'));

        self::assertTrue(TestEntityChangePurgeSwitcher::getGlobalOverride());

        $this->extension->testFinished();

        self::assertFalse(TestEntityChangePurgeSwitcher::getGlobalOverride());
    }

    public function testTestWithoutFinishedIsRestoredWhenNextTestStarts(): void
    {
        // e.g. setUp() failed, so no "Finished" event was emitted
        $this->extension->testPreparationStarted(self::testMethod(WithMethodAttributeDummy::class, 'testWithAttribute'));
        $this->extension->testPreparationStarted(self::testMethod(WithMethodAttributeDummy::class, 'testWithoutAttribute'));

        self::assertNull(TestEntityChangePurgeSwitcher::getGlobalOverride());
    }

    public function testTestWithoutFinishedIsRestoredWhenTestSuiteIsFinished(): void
    {
        $this->extension->testSuiteStarted(self::testSuite(WithMethodAttributeDummy::class));
        $this->extension->testPreparationStarted(self::testMethod(WithMethodAttributeDummy::class, 'testWithAttribute'));
        $this->extension->testSuiteFinished(self::testSuite(WithMethodAttributeDummy::class));

        self::assertNull(TestEntityChangePurgeSwitcher::getGlobalOverride());
    }

    public function testTestSuiteWithoutFinishedIsRestoredWhenNextTestSuiteStarts(): void
    {
        // e.g. setUpBeforeClass() failed on PHPUnit 10, so no "Finished" event was emitted
        $this->extension->testSuiteStarted(self::testSuite(WithClassAttributeDummy::class));
        $this->extension->testSuiteStarted(self::testSuite(WithMethodAttributeDummy::class));

        self::assertNull(TestEntityChangePurgeSwitcher::getGlobalOverride());
    }

    /**
     * @param class-string $className
     */
    private static function testSuite(string $className): TestSuiteForTestClass
    {
        $arguments = [
            'name' => $className,
            'size' => 1,
            'tests' => TestCollection::fromArray([]),
            'file' => __FILE__,
            'line' => __LINE__,
        ];

        // the "$prettifiedName" argument was added in PHPUnit 13.3
        if (method_exists(TestSuiteForTestClass::class, 'prettifiedName')) {
            $arguments['prettifiedName'] = $className;
        }

        return new TestSuiteForTestClass(...$arguments);
    }

    /**
     * @param class-string $className
     */
    private static function testMethod(string $className, string $methodName): TestMethod
    {
        return new TestMethod(
            $className,
            $methodName,
            __FILE__,
            __LINE__,
            new TestDox($className, $methodName, $methodName),
            MetadataCollection::fromArray([]),
            TestDataCollection::fromArray([]),
        );
    }
}
