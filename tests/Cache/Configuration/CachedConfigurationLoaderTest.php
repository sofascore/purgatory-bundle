<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Cache\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\PropertyValues;
use Sofascore\PurgatoryBundle\Cache\Configuration\CachedConfigurationLoader;
use Sofascore\PurgatoryBundle\Cache\Configuration\ConfigurationLoader;
use Sofascore\PurgatoryBundle\Cache\Subscription\PurgeSubscription;
use Sofascore\PurgatoryBundle\Cache\Subscription\PurgeSubscriptionProviderInterface;
use Sofascore\PurgatoryBundle\Listener\Enum\Action;
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
        $this->filepath = $this->tempDir.'/purgatory/subscriptions.php';
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
                new PurgeSubscription(
                    class: 'Foo',
                    property: 'bar',
                    routeParams: ['bar' => new PropertyValues('bar.id')],
                    routeName: 'app_route_foo',
                    route: new Route('/foo'),
                    actions: [Action::Create],
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
