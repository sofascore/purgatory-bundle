<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Maker;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use Sofascore\PurgatoryBundle\Maker\MakeRouteProvider;
use Sofascore\PurgatoryBundle\Tests\Functional\AbstractKernelTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(MakeRouteProvider::class)]
final class MakeRouteProviderTest extends AbstractKernelTestCase
{
    private string|false $colSize;
    private CommandTester $command;

    protected function setUp(): void
    {
        $this->colSize = getenv('COLUMNS');
        putenv('COLUMNS=300');

        self::initializeApplication(['test_case' => 'TestApplication', 'config' => 'app_config.yaml']);

        $this->command = new CommandTester((new Application(self::$kernel))->find('make:purgatory-provider'));
    }

    protected function tearDown(): void
    {
        putenv($this->colSize ? 'COLUMNS='.$this->colSize : 'COLUMNS');

        unset(
            $this->colSize,
            $this->command,
        );

        (new Filesystem())->remove(__DIR__.'/../Functional/TestApplication/Generated/');

        parent::tearDown();
    }

    #[TestWith([
        'input' => ['PlaneRouteProvider', 'Plane', 'no'],
        'expected' => 'PlaneRouteProvider',
    ])]
    #[TestWith([
        'input' => ['Plane', 'Plane', 'no'],
        'expected' => 'PlaneRouteProvider',
    ])]
    #[TestWith([
        'input' => ['ShipBuild', 'Ship', 'yes', '0'],
        'expected' => 'ShipBuildRouteProvider',
    ])]
    #[TestWith([
        'input' => ['VehicleFix', 'Vehicle', 'yes', '1,2'],
        'expected' => 'VehicleFixRouteProvider',
    ])]
    #[TestWith([
        'input' => ['AnimalCompetition', 'Animal', '1', 'yes', '0,1,2'],
        'expected' => 'AnimalCompetitionRouteProvider',
    ])]
    public function testGenerateRouteProvider(array $input, string $expected): void
    {
        $this->command->setInputs([implode(\PHP_EOL, $input).\PHP_EOL]);
        $this->command->execute([], ['interactive' => true]);

        self::assertFileExists(__DIR__."/../Functional/TestApplication/Generated/Purgatory/RouteProvider/$expected.php");
        self::assertFileEquals(
            expected: __DIR__."/Expected/$expected.txt",
            actual: __DIR__."/../Functional/TestApplication/Generated/Purgatory/RouteProvider/$expected.php",
        );
    }

    public function testInvalidEntityInput(): void
    {
        $input = ['foo', 'BlogPost'];
        $this->command->setInputs([implode(\PHP_EOL, $input).\PHP_EOL]);
        $this->command->execute([], ['interactive' => true]);

        self::assertStringContainsString('[ERROR] No entities found', $this->command->getDisplay());
    }
}
