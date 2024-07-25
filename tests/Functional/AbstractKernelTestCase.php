<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

abstract class AbstractKernelTestCase extends KernelTestCase
{
    public static function tearDownAfterClass(): void
    {
        $fileSystem = new Filesystem();

        if (!$fileSystem->exists($dir = static::getVarDir())) {
            return;
        }

        $fileSystem->remove($dir);
    }

    protected static function initializeApplication(array $options = []): void
    {
        if (!static::$booted) {
            static::bootKernel($options);
        }

        self::runCommand(self::$kernel, 'doctrine:schema:drop', ['--force' => true]);
        self::runCommand(self::$kernel, 'doctrine:schema:create');
    }

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected static function createKernel(array $options = []): KernelInterface
    {
        $class = self::getKernelClass();

        if (!isset($options['test_case'])) {
            throw new \InvalidArgumentException('The option "test_case" must be set.');
        }

        return new $class(
            static::getVarDir(),
            $options['test_case'],
            $options['config'] ?? '',
            $options['environment'] ?? 'test',
            $options['debug'] ?? false,
        );
    }

    private static function getVarDir(): string
    {
        return sys_get_temp_dir().'/Purgatory_'.substr(strrchr(static::class, '\\'), 1);
    }

    private static function runCommand(KernelInterface $kernel, string $command, array $parameters = []): void
    {
        $application = new Application($kernel);
        $application->setAutoExit(false);

        $exitCode = $application->run(
            new ArrayInput(['command' => $command, ...$parameters]),
            $output = new BufferedOutput(),
        );

        if (0 !== $exitCode) {
            throw new \RuntimeException(\sprintf('An error occurred while running the "%s" command: %s', $command, $output->fetch()));
        }
    }
}
