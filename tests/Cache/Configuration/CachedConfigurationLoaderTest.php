<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Cache\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle2\Cache\Configuration\CachedConfigurationLoader;
use Sofascore\PurgatoryBundle2\Cache\Configuration\ConfigurationLoader;
use Sofascore\PurgatoryBundle2\Cache\Metadata\PurgeSubscription;
use Sofascore\PurgatoryBundle2\Cache\Metadata\PurgeSubscriptionProviderInterface;
use Sofascore\PurgatoryBundle2\Listener\Enum\Action;
use Symfony\Component\Filesystem\Filesystem;
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
        (new Filesystem())->remove($this->tempDir);
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
                    actions: [Action::Update],
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

        self::assertFileMatchesFormatFile(
            formatFile: __DIR__.'/Fixtures/subscriptions.php',
            actualFile: $this->filepath,
        );
    }
}
