<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Cache\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle2\Cache\Configuration\ConfigurationLoader;
use Sofascore\PurgatoryBundle2\Cache\Metadata\PurgeSubscription;
use Sofascore\PurgatoryBundle2\Cache\Metadata\PurgeSubscriptionProviderInterface;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Routing\Route;

#[CoversClass(ConfigurationLoader::class)]
final class ConfigurationLoaderTest extends TestCase
{
    #[DataProvider('purgeSubscriptionProvider')]
    public function testSubscriptions(array $purgeSubscriptions, array $expectedConfiguration): void
    {
        $purgeSubscriptionProvider = $this->createMock(PurgeSubscriptionProviderInterface::class);
        $purgeSubscriptionProvider->method('provide')
            ->willReturn($purgeSubscriptions);

        $loader = new ConfigurationLoader($purgeSubscriptionProvider);

        self::assertSame($expectedConfiguration, $loader->load());
    }

    public static function purgeSubscriptionProvider(): iterable
    {
        yield 'purge subscription without property' => [
            'purgeSubscriptions' => [
                new PurgeSubscription(
                    class: 'Foo',
                    property: null,
                    routeParams: [],
                    routeName: 'app_route_foo',
                    route: new Route('/foo'),
                    if: null,
                ),
            ],
            'expectedConfiguration' => [
                'Foo' => [
                    [
                        'routeName' => 'app_route_foo',
                        'routeParams' => [],
                        'if' => null,
                    ],
                ],
            ],
        ];

        yield 'purge subscription with property' => [
            'purgeSubscriptions' => [
                new PurgeSubscription(
                    class: 'Foo',
                    property: 'bar',
                    routeParams: [],
                    routeName: 'app_route_foo',
                    route: new Route('/foo'),
                    if: null,
                ),
            ],
            'expectedConfiguration' => [
                'Foo::bar' => [
                    [
                        'routeName' => 'app_route_foo',
                        'routeParams' => [],
                        'if' => null,
                    ],
                ],
            ],
        ];

        yield 'multiple purge subscriptions with and without properties' => [
            'purgeSubscriptions' => [
                new PurgeSubscription(
                    class: 'Foo',
                    property: 'bar',
                    routeParams: [],
                    routeName: 'app_route_foo',
                    route: new Route('/foo'),
                    if: null,
                ),
                new PurgeSubscription(
                    class: 'Foo',
                    property: null,
                    routeParams: [],
                    routeName: 'app_route_bar',
                    route: new Route('/bar'),
                    if: new Expression('expression'),
                ),
                new PurgeSubscription(
                    class: 'Foo',
                    property: 'bar',
                    routeParams: [],
                    routeName: 'app_route_bar',
                    route: new Route('/bar'),
                    if: new Expression('expression'),
                ),
                new PurgeSubscription(
                    class: 'Bar',
                    property: null,
                    routeParams: ['param' => 'value'],
                    routeName: 'app_route_baz',
                    route: new Route('/bar'),
                    if: null,
                ),
            ],
            'expectedConfiguration' => [
                'Foo::bar' => [
                    [
                        'routeName' => 'app_route_foo',
                        'routeParams' => [],
                        'if' => null,
                    ],
                    [
                        'routeName' => 'app_route_bar',
                        'routeParams' => [],
                        'if' => 'expression',
                    ],
                ],
                'Foo' => [
                    [
                        'routeName' => 'app_route_bar',
                        'routeParams' => [],
                        'if' => 'expression',
                    ],
                ],
                'Bar' => [
                    [
                        'routeName' => 'app_route_baz',
                        'routeParams' => ['param' => 'value'],
                        'if' => null,
                    ],
                ],
            ],
        ];
    }
}
