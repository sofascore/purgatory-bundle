<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use Sofascore\PurgatoryBundle\Command\DebugCommand;
use Sofascore\PurgatoryBundle\Tests\Functional\AbstractKernelTestCase;
use Sofascore\PurgatoryBundle\Tests\Functional\DebugCommand\Entity\Author;
use Sofascore\PurgatoryBundle\Tests\Functional\DebugCommand\Entity\Post;
use Sofascore\PurgatoryBundle\Tests\Functional\DebugCommand\Enum\LanguageCodes;
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

        self::initializeApplication(['test_case' => 'DebugCommand']);

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
            expectedNumberOfSubscriptions: 10,
        );
        self::assertNumberOfDisplayedEntities(
            command: $this->command,
            expectedNumberOfEntities: 4,
            entityClass: Post::class,
        );
        self::assertNumberOfDisplayedEntities(
            command: $this->command,
            expectedNumberOfEntities: 5,
            entityClass: Author::class,
        );

        $display = $this->command->getDisplay();

        self::assertSubstringCount(
            expectedCount: 3,
            needle: 'author_id: Property("id")',
            haystack: $display,
        );
        self::assertSubstringCount(
            expectedCount: 2,
            needle: 'post_id: Property("posts[*].id")',
            haystack: $display,
        );
        self::assertSubstringCount(
            expectedCount: 2,
            needle: 'tag_id: Property("posts[*].tags[*].id")',
            haystack: $display,
        );
        self::assertSubstringCount(
            expectedCount: 3,
            needle: \sprintf('lang: Compound(Enum(%s), Raw("XK"))', json_encode(ltrim(LanguageCodes::class, '\\'))),
            haystack: $display,
        );
        self::assertSubstringCount(
            expectedCount: 3,
            needle: 'page: Dynamic("purgatory.get_page", null)',
            haystack: $display,
        );
    }

    public function testOptionRoute(): void
    {
        $this->command->execute([
            '--route' => 'post_show',
        ]);

        $this->command->assertCommandIsSuccessful();

        self::assertNumberOfDisplayedSubscriptions(
            command: $this->command,
            expectedNumberOfSubscriptions: 3,
        );
        self::assertNumberOfDisplayedRoutes(
            command: $this->command,
            expectedNumberOfRoutes: 3,
            route: 'post_show',
        );
        self::assertNumberOfDisplayedEntities(
            command: $this->command,
            expectedNumberOfEntities: 1,
            entityClass: Post::class,
        );
        self::assertNumberOfDisplayedEntities(
            command: $this->command,
            expectedNumberOfEntities: 2,
            entityClass: Author::class,
        );
        self::assertNumberOfDisplayedActions(
            command: $this->command,
            expectedNumberOfActions: 2,
            actions: 'update, delete',
        );
    }

    #[TestWith([Post::class, 1, Post::class, 'ANY'])]
    #[TestWith([Author::class, 1, Author::class, 'ANY'])]
    #[TestWith([Author::class.'::firstName', 2, Author::class, 'firstName'])]
    public function testOptionSubscription(string $subscriptionOption, int $numberOfSubscriptions, string $entity, string $property): void
    {
        $this->command->execute([
            '--subscription' => $subscriptionOption,
        ]);

        $this->command->assertCommandIsSuccessful();

        self::assertNumberOfDisplayedSubscriptions(
            command: $this->command,
            expectedNumberOfSubscriptions: $numberOfSubscriptions,
        );
        self::assertNumberOfDisplayedEntities(
            command: $this->command,
            expectedNumberOfEntities: $numberOfSubscriptions,
            entityClass: $entity,
        );
        self::assertNumberOfDisplayedProperties(
            command: $this->command,
            expectedNumberOfProperties: $numberOfSubscriptions,
            property: $property,
        );
    }

    public function testOptionSubscriptionWithProperties(): void
    {
        $this->command->execute([
            '--subscription' => Author::class,
            '--with-properties' => true,
        ]);

        $this->command->assertCommandIsSuccessful();

        self::assertNumberOfDisplayedSubscriptions(
            command: $this->command,
            expectedNumberOfSubscriptions: 5,
        );
        self::assertNumberOfDisplayedEntities(
            command: $this->command,
            expectedNumberOfEntities: 5,
            entityClass: Author::class,
        );
    }

    public function testInteractiveMode(): void
    {
        $this->command->setInputs([
            0, // Author
            4, // lastName
        ]);
        $this->command->execute([]);

        $this->command->assertCommandIsSuccessful();

        self::assertNumberOfDisplayedEntities(
            command: $this->command,
            expectedNumberOfEntities: 2,
            entityClass: Author::class,
        );
        self::assertNumberOfDisplayedProperties(
            command: $this->command,
            expectedNumberOfProperties: 2,
            property: 'lastName',
        );
    }

    public function testInteractiveModeWithAllProperties(): void
    {
        $this->command->setInputs([
            0, // Author
            0, // *
        ]);
        $this->command->execute([]);

        $this->command->assertCommandIsSuccessful();

        self::assertNumberOfDisplayedEntities(
            command: $this->command,
            expectedNumberOfEntities: 5,
            entityClass: Author::class,
        );
        self::assertNumberOfDisplayedProperties(
            command: $this->command,
            expectedNumberOfProperties: 1,
            property: 'ANY',
        );
        self::assertNumberOfDisplayedProperties(
            command: $this->command,
            expectedNumberOfProperties: 2,
            property: 'firstName',
        );
        self::assertNumberOfDisplayedProperties(
            command: $this->command,
            expectedNumberOfProperties: 2,
            property: 'lastName',
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
                '--subscription' => Author::class.'::firstName',
                '--with-properties' => true,
            ],
            'expectedMessage' => 'The "--with-properties" option requires an entity FQCN without the property path (::firstName).',
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
        self::assertSubstringCount(
            expectedCount: $expectedNumberOfSubscriptions,
            needle: 'Option         Value',
            haystack: $command->getDisplay(),
            message: \sprintf('Failed asserting that %d subscriptions were displayed.', $expectedNumberOfSubscriptions),
        );
    }

    private static function assertNumberOfDisplayedRoutes(
        CommandTester $command,
        int $expectedNumberOfRoutes,
        string $route,
    ): void {
        self::assertSubstringCount(
            expectedCount: $expectedNumberOfRoutes,
            needle: \sprintf('Route Name     %s', $route),
            haystack: $command->getDisplay(),
            message: \sprintf('Failed asserting that %d routes with the name "%s" were displayed.', $expectedNumberOfRoutes, $route),
        );
    }

    private static function assertNumberOfDisplayedEntities(
        CommandTester $command,
        int $expectedNumberOfEntities,
        string $entityClass,
    ): void {
        self::assertSubstringCount(
            expectedCount: $expectedNumberOfEntities,
            needle: \sprintf('Entity         %s', ltrim($entityClass, '\\')),
            haystack: $command->getDisplay(),
            message: \sprintf('Failed asserting that %d entities of class "%s" were displayed.', $expectedNumberOfEntities, $entityClass),
        );
    }

    private static function assertNumberOfDisplayedProperties(
        CommandTester $command,
        int $expectedNumberOfProperties,
        string $property,
    ): void {
        self::assertSubstringCount(
            expectedCount: $expectedNumberOfProperties,
            needle: \sprintf('Property       %s', $property),
            haystack: $command->getDisplay(),
            message: \sprintf('Failed asserting that %d properties with the name "%s" were displayed.', $expectedNumberOfProperties, $property),
        );
    }

    private static function assertNumberOfDisplayedActions(
        CommandTester $command,
        int $expectedNumberOfActions,
        string $actions,
    ): void {
        self::assertSubstringCount(
            expectedCount: $expectedNumberOfActions,
            needle: \sprintf('Actions        %s', $actions),
            haystack: $command->getDisplay(),
            message: \sprintf('Failed asserting that %d actions with the value "%s" were displayed.', $expectedNumberOfActions, $actions),
        );
    }

    private static function assertSubstringCount(
        int $expectedCount,
        string $needle,
        string $haystack,
        string $message = '',
    ): void {
        self::assertSame(
            expected: $expectedCount,
            actual: substr_count($haystack, $needle),
            message: $message,
        );
    }
}
