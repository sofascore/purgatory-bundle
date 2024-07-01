<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use Sofascore\PurgatoryBundle2\Command\DebugCommand;
use Sofascore\PurgatoryBundle2\Tests\Functional\AbstractKernelTestCase;
use Sofascore\PurgatoryBundle2\Tests\Functional\TestApplication\Entity\Animal;
use Sofascore\PurgatoryBundle2\Tests\Functional\TestApplication\Entity\Person;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(DebugCommand::class)]
final class DebugCommandTest extends AbstractKernelTestCase
{
    private string|false $colSize;
    private CommandTester $command;

    protected function setUp(): void
    {
        $this->colSize = getenv('COLUMNS');
        putenv('COLUMNS=300');

        self::initializeApplication(['test_case' => 'TestApplication']);

        $this->command = new CommandTester(
            command: (new Application(self::$kernel))->find('purgatory:debug'),
        );
    }

    protected function tearDown(): void
    {
        putenv($this->colSize ? 'COLUMNS='.$this->colSize : 'COLUMNS');

        unset(
            $this->colSize,
            $this->command,
        );

        parent::tearDown();
    }

    public function testOptionAll(): void
    {
        $this->command->execute([
            '--all' => true,
        ]);

        $this->command->assertCommandIsSuccessful();

        self::assertNumberOfDisplayedSubscriptions(
            command: $this->command,
            expectedNumberOfSubscriptions: 41,
        );
    }

    public function testOptionRoute(): void
    {
        $this->command->execute([
            '--route' => 'animal_route_1',
        ]);

        $this->command->assertCommandIsSuccessful();

        self::assertNumberOfDisplayedSubscriptions(
            command: $this->command,
            expectedNumberOfSubscriptions: 2,
        );

        self::assertSame(
            expected: 2,
            actual: substr_count($this->command->getDisplay(), 'Route Name     animal_route_1'),
        );
    }

    #[TestWith([Person::class, 6])]
    #[TestWith([Animal::class, 4])]
    #[TestWith([Animal::class.'::measurements.height', 8])]
    public function testOptionSubscription(string $subscriptionOption, int $numberOfSubscriptions): void
    {
        $this->command->execute([
            '--subscription' => $subscriptionOption,
        ]);

        $this->command->assertCommandIsSuccessful();

        self::assertNumberOfDisplayedSubscriptions(
            command: $this->command,
            expectedNumberOfSubscriptions: $numberOfSubscriptions,
        );
    }

    public function testOptionSubscriptionWithProperties(): void
    {
        $this->command->execute([
            '--subscription' => Animal::class,
            '--with-properties' => true,
        ]);

        $this->command->assertCommandIsSuccessful();

        self::assertNumberOfDisplayedSubscriptions(
            command: $this->command,
            expectedNumberOfSubscriptions: 34,
        );
    }

    public function testInteractiveMode(): void
    {
        $this->command->setInputs([
            0, // Animal
            2, // measurements.height
        ]);
        $this->command->execute([]);

        $this->command->assertCommandIsSuccessful();

        $display = $this->command->getDisplay();

        self::assertSame(
            expected: 8,
            actual: substr_count($display, 'Entity         Sofascore\PurgatoryBundle2\Tests\Functional\TestApplication\Entity\Animal'),
        );
        self::assertSame(
            expected: 8,
            actual: substr_count($display, 'Property       measurements.height'),
        );
    }

    public function testInteractiveModeWithAllProperties(): void
    {
        $this->command->setInputs([
            0, // Animal
            0, // *
        ]);
        $this->command->execute([]);

        $this->command->assertCommandIsSuccessful();

        $display = $this->command->getDisplay();

        self::assertSame(
            expected: 34,
            actual: substr_count($display, 'Entity         Sofascore\PurgatoryBundle2\Tests\Functional\TestApplication\Entity\Animal'),
        );
        self::assertSame(
            expected: 5,
            actual: substr_count($display, 'Property       measurements '),
        );
        self::assertSame(
            expected: 4,
            actual: substr_count($display, 'Property       name'),
        );
        self::assertSame(
            expected: 8,
            actual: substr_count($display, 'Property       measurements.height'),
        );
        self::assertSame(
            expected: 6,
            actual: substr_count($display, 'Property       measurements.width'),
        );
        self::assertSame(
            expected: 7,
            actual: substr_count($display, 'Property       measurements.weight'),
        );
        self::assertSame(
            expected: 4,
            actual: substr_count($display, 'Property       ANY'),
        );
    }

    #[DataProvider('invalidCommandUsageProvider')]
    public function testInvalidUsage(array $input, string $expectedMessage): void
    {
        $this->command->execute($input);

        self::assertSame(Command::FAILURE, $this->command->getStatusCode());
        self::assertStringContainsString($expectedMessage, $this->command->getDisplay());
    }

    public static function invalidCommandUsageProvider(): iterable
    {
        yield 'option --with-properties with explicit property' => [
            'input' => [
                '--subscription' => Animal::class.'::measurements.height',
                '--with-properties' => true,
            ],
            'expectedMessage' => 'The "--with-properties" option requires an entity FQCN without the property path (::measurements.height).',
        ];

        yield 'nonexistent subscription' => [
            'input' => [
                '--subscription' => 'foo',
            ],
            'expectedMessage' => 'No purge subscriptions found matching "foo".',
        ];

        yield 'nonexistent route' => [
            'input' => [
                '--route' => 'foo_route',
            ],
            'expectedMessage' => 'No purge subscriptions found for route "foo_route".',
        ];

        yield 'invalid target' => [
            'input' => [
                'target' => 'foo',
            ],
            'expectedMessage' => 'No purge subscriptions found matching "foo".',
        ];
    }

    private static function assertNumberOfDisplayedSubscriptions(
        CommandTester $command,
        int $expectedNumberOfSubscriptions,
    ): void {
        self::assertSame(
            expected: $expectedNumberOfSubscriptions,
            actual: substr_count($command->getDisplay(), 'Option         Value'),
            message: sprintf('Failed asserting that %d subscriptions were displayed.', $expectedNumberOfSubscriptions),
        );
    }
}
