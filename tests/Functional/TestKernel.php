<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Functional;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Sofascore\PurgatoryBundle2\Purgatory2Bundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\HttpKernel\Kernel;

final class TestKernel extends Kernel
{
    private readonly string $config;

    public function __construct(
        private readonly string $varDir,
        private readonly string $testCase,
        string $config,
        string $environment,
        bool $debug,
    ) {
        if (!is_dir($this->getProjectDir())) {
            throw new \InvalidArgumentException(sprintf('The test case "%s" does not exist.', $testCase));
        }

        if ('' !== $config && !is_file($config = $this->getProjectDir().'/config/'.$config)) {
            throw new \InvalidArgumentException(sprintf('The config "%s" does not exist.', $config));
        }

        $this->config = $config;

        parent::__construct($environment, $debug);
    }

    protected function getContainerClass(): string
    {
        return parent::getContainerClass().substr(md5($this->config), -16);
    }

    public function getProjectDir(): string
    {
        return __DIR__.'/'.$this->testCase;
    }

    public function getCacheDir(): string
    {
        return $this->varDir.'/'.$this->testCase.'/cache/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return $this->varDir.'/'.$this->testCase.'/logs';
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new DoctrineBundle();
        yield new Purgatory2Bundle();
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container) use ($loader) {
            if (is_dir($dir = $this->getProjectDir().'/Controller')) {
                /** @var PhpFileLoader $phpLoader */
                $phpLoader = $loader->getResolver()->resolve(__FILE__, 'php');
                $phpLoader->registerClasses(
                    (new Definition())->setAutowired(true)->setAutoconfigured(true),
                    'Sofascore\PurgatoryBundle2\Tests\Functional\\'.$this->testCase.'\Controller\\',
                    $dir,
                );

                $container->loadFromExtension('framework', [
                    'test' => true,
                    'serializer' => ['enabled' => true],
                    'router' => [
                        'resource' => $dir,
                        'type' => 5 === Kernel::MAJOR_VERSION ? 'annotation' : 'attribute',
                    ],
                ]);
            }

            if (is_dir($dir = $this->getProjectDir().'/Entity')) {
                $container->setParameter('database_url', 'sqlite:///:memory:');
                $container->loadFromExtension('doctrine', [
                    'dbal' => [
                        'url' => '%env(string:default:database_url:DATABASE_URL)%',
                    ],
                    'orm' => [
                        'mappings' => [
                            'App' => [
                                'type' => 'attribute',
                                'is_bundle' => false,
                                'dir' => $dir,
                                'prefix' => 'Sofascore\PurgatoryBundle2\Tests\Functional\\'.$this->testCase.'\Entity',
                                'alias' => 'App',
                            ],
                        ],
                    ],
                ]);
            }

            $container->loadFromExtension('sofascore_purgatory', [
                'purger' => 'in-memory',
            ]);
        });

        if ('' !== $this->config) {
            $loader->load($this->config);
        }
    }
}
