<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Cache\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\CompoundValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\EnumValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\PropertyValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\RawValues;
use Sofascore\PurgatoryBundle\Cache\Configuration\Configuration;
use Sofascore\PurgatoryBundle\Cache\Configuration\ConfigurationLoader;
use Sofascore\PurgatoryBundle\Cache\Subscription\PurgeSubscription;
use Sofascore\PurgatoryBundle\Cache\Subscription\PurgeSubscriptionProviderInterface;
use Sofascore\PurgatoryBundle\Listener\Enum\Action;
use Sofascore\PurgatoryBundle\Tests\Fixtures\DummyStringEnum;
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

        self::assertInstanceOf(Configuration::class, $configuration = $loader->load());
        self::assertSame($expectedConfiguration, $configuration->toArray());
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
                    actions: Action::cases(),
                    if: null,
                    context: [],
                ),
            ],
            'expectedConfiguration' => [
                'Foo' => [
                    [
                        'routeName' => 'app_route_foo',
                        'actions' => Action::cases(),
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
                    actions: [Action::Create],
                    if: null,
                    context: [],
                ),
            ],
            'expectedConfiguration' => [
                'Foo::bar' => [
                    [
                        'routeName' => 'app_route_foo',
                        'actions' => [Action::Create],
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
                    actions: null,
                    if: null,
                    context: [],
                ),
                new PurgeSubscription(
                    class: 'Foo',
                    property: null,
                    routeParams: [],
                    routeName: 'app_route_bar',
                    route: new Route('/bar'),
                    actions: null,
                    if: new Expression('expression'),
                    context: ['qux' => true, 'corge' => 2],
                ),
                new PurgeSubscription(
                    class: 'Foo',
                    property: 'bar',
                    routeParams: [],
                    routeName: 'app_route_bar',
                    route: new Route('/bar'),
                    actions: null,
                    if: new Expression('expression'),
                    context: [],
                ),
                new PurgeSubscription(
                    class: 'Bar',
                    property: null,
                    routeParams: [
                        'param' => new PropertyValues('value'),
                    ],
                    routeName: 'app_route_baz',
                    route: new Route('/bar'),
                    actions: null,
                    if: null,
                    context: [],
                ),
                new PurgeSubscription(
                    class: 'Bar',
                    property: null,
                    routeParams: [
                        'param1' => new PropertyValues('value1'),
                        'param2' => new PropertyValues('value2'),
                    ],
                    routeName: 'app_route_baz_2',
                    route: new Route('/bar/{param1}/{param2}', defaults: ['param2' => null]),
                    actions: [Action::Update],
                    if: null,
                    context: [],
                ),
            ],
            'expectedConfiguration' => [
                'Foo::bar' => [
                    [
                        'routeName' => 'app_route_foo',
                    ],
                    [
                        'routeName' => 'app_route_bar',
                        'if' => 'expression',
                    ],
                ],
                'Foo' => [
                    [
                        'routeName' => 'app_route_bar',
                        'if' => 'expression',
                        'context' => ['qux' => true, 'corge' => 2],
                    ],
                ],
                'Bar' => [
                    [
                        'routeName' => 'app_route_baz',
                        'routeParams' => [
                            'param' => [
                                'type' => PropertyValues::type(),
                                'values' => ['value'],
                            ],
                        ],
                    ],
                    [
                        'routeName' => 'app_route_baz_2',
                        'routeParams' => [
                            'param1' => [
                                'type' => PropertyValues::type(),
                                'values' => ['value1'],
                            ],
                            'param2' => [
                                'type' => PropertyValues::type(),
                                'values' => ['value2'],
                                'optional' => true,
                            ],
                        ],
                        'actions' => [Action::Update],
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
                    actions: null,
                    if: null,
                    context: [],
                ),
            ],
            'expectedConfiguration' => [
                'Foo' => [
                    [
                        'routeName' => 'app_route_foo',
                        'routeParams' => [
                            'foo' => [
                                'type' => RawValues::type(),
                                'values' => ['foo', 1],
                            ],
                            'bar' => [
                                'type' => EnumValues::type(),
                                'values' => [DummyStringEnum::class],
                            ],
                            'baz' => [
                                'type' => CompoundValues::type(),
                                'values' => [
                                    [
                                        'type' => RawValues::type(),
                                        'values' => ['foo', 1],
                                    ],
                                    [
                                        'type' => EnumValues::type(),
                                        'values' => [DummyStringEnum::class],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
