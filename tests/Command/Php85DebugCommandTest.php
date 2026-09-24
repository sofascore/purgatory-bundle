<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhp;
use Sofascore\PurgatoryBundle\Command\DebugCommand;
use Sofascore\PurgatoryBundle\Tests\Functional\AbstractKernelTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(DebugCommand::class)]
#[RequiresPhp('>= 8.5.0')]
final class Php85DebugCommandTest extends AbstractKernelTestCase
{
    private string|false $colSize;
    private CommandTester $command;

    protected function setUp(): void
    {
        $this->colSize = getenv('COLUMNS');
        putenv('COLUMNS=300');

        self::initializeApplication(['test_case' => 'Php85TestApplication', 'config' => 'app_config.yaml']);

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

    public function testClosureIfIsRendered(): void
    {
        $this->command->execute([
            '--all' => true,
        ]);

        $this->command->assertCommandIsSuccessful();

        $expectedClosure = <<<'PHP'
            Condition      static function (Plant $plant): bool {
                                 return 0 === $plant->getWaterLevel();
                             }
            PHP;

        self::assertStringContainsString(
            needle: $expectedClosure,
            haystack: preg_replace('/ +$/m', '', $this->command->getDisplay()),
        );
    }

    public function testInversePropertyPathIsRendered(): void
    {
        $this->command->execute([
            '--route' => 'garden_plants_list',
        ]);

        $this->command->assertCommandIsSuccessful();

        $display = preg_replace('/ +$/m', '', $this->command->getDisplay());

        self::assertStringContainsString(
            needle: <<<'PHP'
                Condition      Called with the value of "garden" (skipped if null):
                                 static function (Garden $garden): bool {
                                     return $garden->isPublic();
                                 }
                PHP,
            haystack: $display,
        );

        self::assertStringContainsString(
            needle: <<<'PHP'
                Condition      Called with the value of "bestInGarden" (skipped if null):
                                 static function (Garden $garden): bool {
                                     return $garden->isPublic();
                                 }
                PHP,
            haystack: $display,
        );

        // the direct subscription on Garden is not navigated through a property
        self::assertSame(2, substr_count($display, 'Called with'));
    }
}
