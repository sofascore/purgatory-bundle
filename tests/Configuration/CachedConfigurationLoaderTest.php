<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle2\Cache\Metadata\PurgeSubscription;
use Sofascore\PurgatoryBundle2\Cache\Metadata\PurgeSubscriptionProviderInterface;
use Sofascore\PurgatoryBundle2\Configuration\CachedConfigurationLoader;
use Sofascore\PurgatoryBundle2\Configuration\ConfigurationLoader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

#[CoversClass(CachedConfigurationLoader::class)]
final class CachedConfigurationLoaderTest extends TestCase
{
    private string $tempDir;
    private string $filepath;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/purgatory_test';
        $this->filepath = $this->tempDir.'/sofascore/purgatory/subscriptions.php';
    }

    protected function tearDown(): void
    {
        $cacheDir = \dirname($this->filepath);
        array_map('unlink', glob($cacheDir.\DIRECTORY_SEPARATOR.'*'));
        rmdir($cacheDir);
        rmdir(\dirname($cacheDir));
        rmdir(\dirname($cacheDir, 2));
        unset($this->tempDir, $this->filepath);
    }

    public function testSubscriptionsAreCached(): void
    {
        $purgeSubscriptionProvider = $this->createMock(PurgeSubscriptionProviderInterface::class);
        $purgeSubscriptionProvider->method('provide')
            ->willReturn([
                new PurgeSubscription(
                    class: 'Foo',
                    property: null,
                    routeParams: [],
                    routeName: 'app_route_foo',
                    route: new Route('/foo'),
                    if: null,
                ),
            ]);

        $router = $this->createMock(RouterInterface::class);
        $router->method('getRouteCollection')
            ->willReturn(new RouteCollection());

        $cachedConfigurationLoader = new CachedConfigurationLoader(
            configurationLoader: new ConfigurationLoader($purgeSubscriptionProvider),
            router: $router,
            buildDir: $this->tempDir,
            debug: false,
        );

        self::assertFileDoesNotExist($this->filepath);

        $cachedConfigurationLoader->load();

        self::assertFileEquals(__DIR__.'/Fixtures/subscriptions.php', $this->filepath);
    }
}
