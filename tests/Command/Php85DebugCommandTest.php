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
}
