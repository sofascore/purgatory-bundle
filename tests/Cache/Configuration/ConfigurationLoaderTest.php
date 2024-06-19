<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Cache\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\CompoundValues;
use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\EnumValues;
use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\PropertyValues;
use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\RawValues;
use Sofascore\PurgatoryBundle2\Cache\Configuration\ConfigurationLoader;
use Sofascore\PurgatoryBundle2\Cache\Metadata\PurgeSubscription;
use Sofascore\PurgatoryBundle2\Cache\Metadata\PurgeSubscriptionProviderInterface;
use Sofascore\PurgatoryBundle2\Tests\Fixtures\DummyStringEnum;
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
                    routeParams: ['param' => new PropertyValues('value')],
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
                        'routeParams' => [
                            'param' => [
                                'type' => PropertyValues::class,
                                'values' => ['value'],
                            ],
                        ],
                        'if' => null,
                    ],
                ],
            ],
        ];

        yield 'subscription with RawValue and EnumValue as route params' => [
            'purgeSubscriptions' => [
                new PurgeSubscription(
                    class: 'Foo',
                    property: null,
                    routeParams: [
                        'foo' => new RawValues('foo', 1),
                        'bar' => new EnumValues(DummyStringEnum::class),
                        'baz' => new CompoundValues(new RawValues('foo', 1), new EnumValues(DummyStringEnum::class)),
                    ],
                    routeName: 'app_route_foo',
                    route: new Route('/foo/{foo}/{bar}'),
                    if: null,
                ),
            ],
            'expectedConfiguration' => [
                'Foo' => [
                    [
                        'routeName' => 'app_route_foo',
                        'routeParams' => [
                            'foo' => [
                                'type' => RawValues::class,
                                'values' => ['foo', 1],
                            ],
                            'bar' => [
                                'type' => EnumValues::class,
                                'values' => [DummyStringEnum::class],
                            ],
                            'baz' => [
                                'type' => CompoundValues::class,
                                'values' => [
                                    [
                                        'type' => RawValues::class,
                                        'values' => ['foo', 1],
                                    ],
                                    [
                                        'type' => EnumValues::class,
                                        'values' => [DummyStringEnum::class],
                                    ],
                                ],
                            ],
                        ],
                        'if' => null,
                    ],
                ],
            ],
        ];
    }
}
