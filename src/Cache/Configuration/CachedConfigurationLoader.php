<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Cache\Configuration;

use Symfony\Component\Config\ConfigCache;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\Routing\RouterInterface;

final class CachedConfigurationLoader implements ConfigurationLoaderInterface, CacheWarmerInterface
{
    public function __construct(
        private readonly ConfigurationLoaderInterface $configurationLoader,
        private readonly RouterInterface $router,
        private readonly ?string $buildDir,
        private readonly bool $debug,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function load(): Configuration
    {
        if (null === $this->buildDir) {
            return $this->configurationLoader->load();
        }

        $cache = new ConfigCache(
            file: $this->buildDir.'/purgatory/subscriptions.php',
            debug: $this->debug,
        );

        if (!$cache->isFresh()) {
            $configuration = $this->configurationLoader->load();

            $cache->write(
                content: '<?php return '.var_export($configuration->toArray(), true).';',
                metadata: $this->router->getRouteCollection()->getResources(),
            );
        }

        return new Configuration(require $cache->getPath());
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        $this->load();

        return [];
    }

    public function isOptional(): bool
    {
        return true;
    }
}
