<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\RouteProvider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\CompoundValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\EnumValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\PropertyValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\RawValues;
use Sofascore\PurgatoryBundle\Cache\Configuration\ConfigurationLoaderInterface;
use Sofascore\PurgatoryBundle\Exception\LogicException;
use Sofascore\PurgatoryBundle\Listener\Enum\Action;
use Sofascore\PurgatoryBundle\RouteParamValueResolver\CompoundValuesResolver;
use Sofascore\PurgatoryBundle\RouteParamValueResolver\EnumValuesResolver;
use Sofascore\PurgatoryBundle\RouteParamValueResolver\PropertyValuesResolver;
use Sofascore\PurgatoryBundle\RouteParamValueResolver\RawValuesResolver;
use Sofascore\PurgatoryBundle\RouteProvider\AbstractEntityRouteProvider;
use Sofascore\PurgatoryBundle\RouteProvider\CreatedEntityRouteProvider;
use Sofascore\PurgatoryBundle\RouteProvider\PropertyAccess\PurgatoryPropertyAccessor;
use Sofascore\PurgatoryBundle\Tests\Fixtures\DummyStringEnum;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\PropertyAccess\PropertyAccess;

#[CoversClass(AbstractEntityRouteProvider::class)]
#[CoversClass(CreatedEntityRouteProvider::class)]
final class CreatedEntityRouteProviderTest extends TestCase
{
    public function testProvideRoutesToPurgeWithoutIf(): void
    {
        $routeProvider = $this->createRouteProvider([
            'stdClass' => [
                [
                    'routeName' => 'foo_route',
                    'routeParams' => [],
                    'if' => null,
                ],
            ],
            'stdClass::foo' => [
                [
                    'routeName' => 'bar_route',
                    'routeParams' => [],
                    'if' => null,
                ],
                [
                    'routeName' => 'baz_route',
                    'routeParams' => [
                        'param1' => [
                            'type' => PropertyValues::type(),
                            'values' => ['foo', 'bar'],
                        ],
                        'param2' => [
                            'type' => PropertyValues::type(),
                            'values' => ['baz'],
                        ],
                    ],
                    'if' => null,
                ],
            ],
        ], false);

        $entity = new \stdClass();
        $entity->foo = 1;
        $entity->bar = 2;
        $entity->baz = 3;

        self::assertTrue($routeProvider->supports(Action::Create, $entity));
        self::assertFalse($routeProvider->supports(Action::Delete, $entity));
        self::assertFalse($routeProvider->supports(Action::Update, $entity));

        $routes = [...$routeProvider->provideRoutesFor(
            action: Action::Create,
            entity: $entity,
            entityChangeSet: [
                'foo' => [null, null],
                'bar' => [null, null],
                'baz' => [null, null],
            ],
        )];

        self::assertCount(4, $routes);
        self::assertSame(['routeName' => 'foo_route', 'routeParams' => []], $routes[0]);
        self::assertSame(['routeName' => 'bar_route', 'routeParams' => []], $routes[1]);
        self::assertSame(['routeName' => 'baz_route', 'routeParams' => ['param1' => 1, 'param2' => 3]], $routes[2]);
        self::assertSame(['routeName' => 'baz_route', 'routeParams' => ['param1' => 2, 'param2' => 3]], $routes[3]);
    }

    public function testProvideRoutesToPurgeWithIf(): void
    {
        $routeProvider = $this->createRouteProvider([
            'stdClass' => [
                [
                    'routeName' => 'foo_route',
                    'if' => 'obj.test == true',
                ],
            ],
            'stdClass::foo' => [
                [
                    'routeName' => 'bar_route',
                    'if' => 'obj.test == true',
                ],
                [
                    'routeName' => 'baz_route',
                    'routeParams' => [
                        'param1' => [
                            'type' => PropertyValues::type(),
                            'values' => ['foo', 'bar'],
                        ],
                        'param2' => [
                            'type' => PropertyValues::type(),
                            'values' => ['baz'],
                        ],
                    ],
                    'if' => 'obj.test == true',
                ],
            ],
        ], true);

        $entity = new \stdClass();

        self::assertTrue($routeProvider->supports(Action::Create, $entity));
        self::assertFalse($routeProvider->supports(Action::Delete, $entity));
        self::assertFalse($routeProvider->supports(Action::Update, $entity));

        $routes = [...$routeProvider->provideRoutesFor(
            action: Action::Create,
            entity: $entity,
            entityChangeSet: [
                'foo' => [null, null],
                'bar' => [null, null],
                'baz' => [null, null],
            ],
        )];

        self::assertCount(2, $routes);
        self::assertSame(['routeName' => 'foo_route', 'routeParams' => []], $routes[0]);
        self::assertSame(['routeName' => 'bar_route', 'routeParams' => []], $routes[1]);
    }

    public function testExceptionIsThrownWhenIfIsUsedWithoutExpressionLangInstalled(): void
    {
        $routeProvider = $this->createRouteProvider([
            'stdClass' => [
                [
                    'routeName' => 'foo_route',
                    'routeParams' => [],
                    'if' => 'obj.test == true',
                ],
            ],
        ], false);

        $entity = new \stdClass();

        $this->expectException(LogicException::class);

        iterator_to_array($routeProvider->provideRoutesFor(Action::Create, $entity, []));
    }

    public function testRouteParamsWithRawValuesAndEnumValues(): void
    {
        $routeProvider = $this->createRouteProvider([
            'stdClass::foo' => [
                [
                    'routeName' => 'foo_route',
                    'routeParams' => [
                        'foo' => [
                            'type' => CompoundValues::type(),
                            'values' => [
                                [
                                    'type' => RawValues::type(),
                                    'values' => ['foo', 1, null],
                                ],
                                [
                                    'type' => EnumValues::type(),
                                    'values' => [DummyStringEnum::class],
                                ],
                            ],
                            'optional' => true,
                        ],
                    ],
                ],
            ],
        ], false);

        $entity = new \stdClass();

        self::assertTrue($routeProvider->supports(Action::Create, $entity));
        self::assertFalse($routeProvider->supports(Action::Delete, $entity));
        self::assertFalse($routeProvider->supports(Action::Update, $entity));

        $routes = [...$routeProvider->provideRoutesFor(
            action: Action::Create,
            entity: $entity,
            entityChangeSet: [
                'foo' => [null, null],
                'bar' => [null, null],
                'baz' => [null, null],
            ],
        )];

        self::assertCount(6, $routes);

        // RawValueResolver
        self::assertSame(['routeName' => 'foo_route', 'routeParams' => ['foo' => 'foo']], $routes[0]);
        self::assertSame(['routeName' => 'foo_route', 'routeParams' => ['foo' => 1]], $routes[1]);
        self::assertSame(['routeName' => 'foo_route', 'routeParams' => ['foo' => null]], $routes[2]);

        // EnumValueResolver
        self::assertSame(['routeName' => 'foo_route', 'routeParams' => ['foo' => 'case1']], $routes[3]);
        self::assertSame(['routeName' => 'foo_route', 'routeParams' => ['foo' => 'case2']], $routes[4]);
        self::assertSame(['routeName' => 'foo_route', 'routeParams' => ['foo' => 'case3']], $routes[5]);
    }

    private function createRouteProvider(array $subscriptions, bool $withExpressionLang): CreatedEntityRouteProvider
    {
        $configurationLoader = $this->createMock(ConfigurationLoaderInterface::class);
        $configurationLoader->method('load')
            ->willReturn($subscriptions);

        $expressionLanguage = null;
        if ($withExpressionLang) {
            $expressionLanguage = $this->createMock(ExpressionLanguage::class);
            $expressionLanguage->method('evaluate')
                ->willReturnOnConsecutiveCalls(true, true, false);
        }

        $propertyAccessor = new PurgatoryPropertyAccessor(PropertyAccess::createPropertyAccessor());

        $routeParamValueResolvers = [
            PropertyValues::type() => static fn () => new PropertyValuesResolver($propertyAccessor),
            EnumValues::type() => static fn () => new EnumValuesResolver(),
            RawValues::type() => static fn () => new RawValuesResolver(),
        ];

        return new CreatedEntityRouteProvider(
            $configurationLoader,
            $expressionLanguage,
            new ServiceLocator($routeParamValueResolvers + [
                CompoundValues::type() => static fn () => new CompoundValuesResolver(new ServiceLocator($routeParamValueResolvers)),
            ]),
        );
    }
}
