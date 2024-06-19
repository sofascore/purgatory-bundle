<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\RouteProvider;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\CompoundValues;
use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\EnumValues;
use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\PropertyValues;
use Sofascore\PurgatoryBundle2\Attribute\RouteParamValue\RawValues;
use Sofascore\PurgatoryBundle2\Cache\Configuration\ConfigurationLoaderInterface;
use Sofascore\PurgatoryBundle2\Exception\LogicException;
use Sofascore\PurgatoryBundle2\Listener\Enum\Action;
use Sofascore\PurgatoryBundle2\RouteParamValueResolver\CompoundValuesResolver;
use Sofascore\PurgatoryBundle2\RouteParamValueResolver\EnumValuesResolver;
use Sofascore\PurgatoryBundle2\RouteParamValueResolver\PropertyValuesResolver;
use Sofascore\PurgatoryBundle2\RouteParamValueResolver\RawValuesResolver;
use Sofascore\PurgatoryBundle2\RouteProvider\RemovedEntityRouteProvider;
use Sofascore\PurgatoryBundle2\Tests\Fixtures\DummyStringEnum;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

#[CoversClass(RemovedEntityRouteProvider::class)]
final class RemovedEntityRouteProviderTest extends TestCase
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
                            'type' => PropertyValues::class,
                            'values' => ['foo', 'bar'],
                        ],
                        'param2' => [
                            'type' => PropertyValues::class,
                            'values' => ['baz'],
                        ],
                    ],
                    'if' => null,
                ],
            ],
        ], false);

        $entity = new \stdClass();

        self::assertTrue($routeProvider->supports(Action::Delete, new \stdClass()));
        self::assertFalse($routeProvider->supports(Action::Create, new \stdClass()));
        self::assertFalse($routeProvider->supports(Action::Update, new \stdClass()));

        $routes = [...$routeProvider->provideRoutesFor(Action::Delete, $entity, [])];

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
                    'routeParams' => [],
                    'if' => 'obj.test == true',
                ],
            ],
            'stdClass::foo' => [
                [
                    'routeName' => 'bar_route',
                    'routeParams' => [],
                    'if' => 'obj.test == true',
                ],
                [
                    'routeName' => 'baz_route',
                    'routeParams' => [
                        'param1' => [
                            'type' => PropertyValues::class,
                            'values' => ['foo', 'bar'],
                        ],
                        'param2' => [
                            'type' => PropertyValues::class,
                            'values' => ['baz'],
                        ],
                    ],
                    'if' => 'obj.test == true',
                ],
            ],
        ], true);

        $entity = new \stdClass();

        self::assertTrue($routeProvider->supports(Action::Delete, new \stdClass()));
        self::assertFalse($routeProvider->supports(Action::Create, new \stdClass()));
        self::assertFalse($routeProvider->supports(Action::Update, new \stdClass()));

        $routes = [...$routeProvider->provideRoutesFor(Action::Delete, $entity, [])];

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

        iterator_to_array($routeProvider->provideRoutesFor(Action::Delete, $entity, []));
    }

    public function testRouteParamsWithRawValuesAndEnumValues(): void
    {
        $routeProvider = $this->createRouteProvider([
            'stdClass' => [
                [
                    'routeName' => 'foo_route',
                    'routeParams' => [
                        'foo' => [
                            'type' => CompoundValues::class,
                            'values' => [
                                [
                                    'type' => RawValues::class,
                                    'values' => ['foo', 1, null],
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
        ], false);

        $entity = new \stdClass();

        self::assertTrue($routeProvider->supports(Action::Delete, new \stdClass()));
        self::assertFalse($routeProvider->supports(Action::Create, new \stdClass()));
        self::assertFalse($routeProvider->supports(Action::Update, new \stdClass()));

        $routes = [...$routeProvider->provideRoutesFor(Action::Delete, $entity, [])];

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

    private function createRouteProvider(array $subscriptions, bool $withExpressionLang): RemovedEntityRouteProvider
    {
        $configurationLoader = $this->createMock(ConfigurationLoaderInterface::class);
        $configurationLoader->method('load')
            ->willReturn($subscriptions);

        $classMetadata = $this->createMock(ClassMetadata::class);

        $entityManager = $this->createMock(EntityManagerInterface::class);

        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $managerRegistry->method('getManagerForClass')
            ->with(\stdClass::class)
            ->willReturn($entityManager);

        $entityManager->method('getClassMetadata')
            ->with(\stdClass::class)
            ->willReturn($classMetadata);

        $classMetadata->method('getFieldNames')
            ->willReturn(['foo', 'bar', 'baz']);

        $classMetadata->method('getAssociationNames')
            ->willReturn([]);

        $propertyAccessor = $this->createMock(PropertyAccessorInterface::class);
        $propertyAccessor->method('getValue')
            ->willReturnCallback(fn (object $entity, string $value) => match ($value) {
                'foo' => 1,
                'bar' => 2,
                'baz' => 3,
            });

        $expressionLanguage = null;
        if ($withExpressionLang) {
            $expressionLanguage = $this->createMock(ExpressionLanguage::class);
            $expressionLanguage->method('evaluate')
                ->willReturnOnConsecutiveCalls(true, true, false);
        }

        $routeParamValueResolvers = [
            PropertyValues::class => static fn () => new PropertyValuesResolver($propertyAccessor),
            EnumValues::class => static fn () => new EnumValuesResolver(),
            RawValues::class => static fn () => new RawValuesResolver(),
        ];

        return new RemovedEntityRouteProvider(
            $configurationLoader,
            $expressionLanguage,
            new ServiceLocator($routeParamValueResolvers + [
                CompoundValues::class => static fn () => new CompoundValuesResolver(new ServiceLocator($routeParamValueResolvers)),
            ]),
            $managerRegistry,
        );
    }
}
