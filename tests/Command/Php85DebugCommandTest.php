<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhp;
use Sofascore\PurgatoryBundle\Command\DebugCommand;
use Sofascore\PurgatoryBundle\Tests\Functional\AbstractKernelTestCase;
use Sofascore\PurgatoryBundle\Tests\Functional\Php85TestApplication\Controller\PlantController;
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
            Condition      Closure defined in Controller/PlantController.php:19

                             static function (Plant $plant): bool {
                                 return 0 === $plant->getWaterLevel();
                             }
            PHP;

        self::assertStringContainsString(
            needle: $expectedClosure,
            haystack: preg_replace('/ +$/m', '', $this->command->getDisplay()),
        );
    }

    public function testClosureIfLinksToSource(): void
    {
        $env = ['TERMINAL_EMULATOR' => getenv('TERMINAL_EMULATOR'), 'KONSOLE_VERSION' => getenv('KONSOLE_VERSION')];
        $ideaInitialDirectory = $_SERVER['IDEA_INITIAL_DIRECTORY'] ?? null;

        // terminals that don't render hyperlinks gracefully get the plain text only
        putenv('TERMINAL_EMULATOR');
        putenv('KONSOLE_VERSION');
        unset($_SERVER['IDEA_INITIAL_DIRECTORY']);

        try {
            $this->command->execute(['--route' => 'dry_plants_list'], ['decorated' => true]);
        } finally {
            foreach ($env as $name => $value) {
                putenv(false === $value ? $name : $name.'='.$value);
            }

            if (null !== $ideaInitialDirectory) {
                $_SERVER['IDEA_INITIAL_DIRECTORY'] = $ideaInitialDirectory;
            }
        }

        $this->command->assertCommandIsSuccessful();

        $file = (new \ReflectionClass(PlantController::class))->getFileName();

        self::assertStringContainsString(
            needle: "\e]8;;purgatory://open?file={$file}&line=19\e\\Controller/PlantController.php:19\e]8;;\e\\",
            haystack: $this->command->getDisplay(),
        );
    }

    public function testClosureIfIsHighlighted(): void
    {
        $this->command->execute(['--route' => 'dry_plants_list'], ['decorated' => true]);

        $this->command->assertCommandIsSuccessful();

        $display = $this->command->getDisplay();

        self::assertStringContainsString("\e[33mstatic\e[39m \e[33mfunction\e[39m (Plant \e[36m\$plant\e[39m): bool {", $display);
        self::assertStringContainsString("\e[33mreturn\e[39m \e[35m0\e[39m === \e[36m\$plant\e[39m->getWaterLevel();", $display);
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
                Condition      Closure defined in Controller/PlantController.php:34

                                 Receives "garden" of the changed entity:
                                 static function (Garden $garden): bool {
                                     return $garden->isPublic();
                                 }
                PHP,
            haystack: $display,
        );

        self::assertStringContainsString(
            needle: <<<'PHP'
                Condition      Closure defined in Controller/PlantController.php:43

                                 Receives "bestInGarden" of the changed entity:
                                 static function (Garden $garden): bool {
                                     return $garden->isPublic();
                                 }
                PHP,
            haystack: $display,
        );

        // the direct subscription on Garden is not navigated through a property
        self::assertSame(2, substr_count($display, 'Receives'));
    }
}
