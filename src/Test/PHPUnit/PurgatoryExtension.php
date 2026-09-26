<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Test\PHPUnit;

use PHPUnit\Event\Code\Test;
use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\Test\Finished;
use PHPUnit\Event\Test\FinishedSubscriber;
use PHPUnit\Event\Test\PreparationStarted;
use PHPUnit\Event\Test\PreparationStartedSubscriber;
use PHPUnit\Event\TestSuite\Finished as TestSuiteFinished;
use PHPUnit\Event\TestSuite\FinishedSubscriber as TestSuiteFinishedSubscriber;
use PHPUnit\Event\TestSuite\Skipped as TestSuiteSkipped;
use PHPUnit\Event\TestSuite\SkippedSubscriber as TestSuiteSkippedSubscriber;
use PHPUnit\Event\TestSuite\Started as TestSuiteStarted;
use PHPUnit\Event\TestSuite\StartedSubscriber as TestSuiteStartedSubscriber;
use PHPUnit\Event\TestSuite\TestSuite;
use PHPUnit\Event\TestSuite\TestSuiteForTestClass;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;
use Sofascore\PurgatoryBundle\Test\TestEntityChangePurgeSwitcher;

/**
 * Enables purging on entity changes for test classes and test methods
 * marked with the "#[WithEntityChangePurging]" attribute.
 *
 * PHPUnit does not report the end of every test and test class, e.g. no
 * "Finished" event is emitted when setUp() fails. Instead of relying on
 * the end events alone, anything left over is also restored when the
 * next test or test class starts.
 */
final class PurgatoryExtension implements Extension
{
    /**
     * The global override from before the attribute was applied, per scope.
     *
     * @var array{class?: ?bool, test?: ?bool}
     */
    private array $previous = [];

    public function __construct(
        private readonly AttributeReader $reader = new AttributeReader(),
    ) {
    }

    /**
     * @codeCoverageIgnore
     */
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        $facade->registerSubscriber(new class($this) implements TestSuiteStartedSubscriber {
            public function __construct(
                private readonly PurgatoryExtension $extension,
            ) {
            }

            public function notify(TestSuiteStarted $event): void
            {
                $this->extension->testSuiteStarted($event->testSuite());
            }
        });

        $facade->registerSubscriber(new class($this) implements TestSuiteFinishedSubscriber {
            public function __construct(
                private readonly PurgatoryExtension $extension,
            ) {
            }

            public function notify(TestSuiteFinished $event): void
            {
                $this->extension->testSuiteFinished($event->testSuite());
            }
        });

        // emitted instead of "Finished" when setUpBeforeClass() skips the test class
        $facade->registerSubscriber(new class($this) implements TestSuiteSkippedSubscriber {
            public function __construct(
                private readonly PurgatoryExtension $extension,
            ) {
            }

            public function notify(TestSuiteSkipped $event): void
            {
                $this->extension->testSuiteFinished($event->testSuite());
            }
        });

        $facade->registerSubscriber(new class($this) implements PreparationStartedSubscriber {
            public function __construct(
                private readonly PurgatoryExtension $extension,
            ) {
            }

            public function notify(PreparationStarted $event): void
            {
                $this->extension->testPreparationStarted($event->test());
            }
        });

        $facade->registerSubscriber(new class($this) implements FinishedSubscriber {
            public function __construct(
                private readonly PurgatoryExtension $extension,
            ) {
            }

            public function notify(Finished $event): void
            {
                $this->extension->testFinished();
            }
        });
    }

    /**
     * @internal
     */
    public function testSuiteStarted(TestSuite $testSuite): void
    {
        if (!$testSuite instanceof TestSuiteForTestClass) {
            return;
        }

        $this->restore('test');
        $this->restore('class');

        if (null !== $this->reader->forClass($testSuite->className())) {
            $this->apply('class');
        }
    }

    /**
     * @internal
     */
    public function testSuiteFinished(TestSuite $testSuite): void
    {
        if (!$testSuite instanceof TestSuiteForTestClass) {
            return;
        }

        $this->restore('test');
        $this->restore('class');
    }

    /**
     * @internal
     */
    public function testPreparationStarted(Test $test): void
    {
        $this->restore('test');

        if ($test instanceof TestMethod && null !== $this->reader->forMethod($test->className(), $test->methodName())) {
            $this->apply('test');
        }
    }

    /**
     * @internal
     */
    public function testFinished(): void
    {
        $this->restore('test');
    }

    /**
     * @param 'class'|'test' $scope
     */
    private function apply(string $scope): void
    {
        $this->previous[$scope] = TestEntityChangePurgeSwitcher::getGlobalOverride();

        TestEntityChangePurgeSwitcher::enable();
    }

    /**
     * @param 'class'|'test' $scope
     */
    private function restore(string $scope): void
    {
        if (!\array_key_exists($scope, $this->previous)) {
            return;
        }

        match ($this->previous[$scope]) {
            true => TestEntityChangePurgeSwitcher::enable(),
            false => TestEntityChangePurgeSwitcher::disable(),
            null => TestEntityChangePurgeSwitcher::reset(),
        };

        unset($this->previous[$scope]);
    }
}
