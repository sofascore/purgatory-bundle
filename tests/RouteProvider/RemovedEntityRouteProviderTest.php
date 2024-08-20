<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\RouteProvider;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\CompoundValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\EnumValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\PropertyValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\RawValues;
use Sofascore\PurgatoryBundle\Cache\Configuration\Configuration;
use Sofascore\PurgatoryBundle\Cache\Configuration\ConfigurationLoaderInterface;
use Sofascore\PurgatoryBundle\Exception\EntityMetadataNotFoundException;
use Sofascore\PurgatoryBundle\Exception\LogicException;
use Sofascore\PurgatoryBundle\Listener\Enum\Action;
use Sofascore\PurgatoryBundle\RouteParamValueResolver\CompoundValuesResolver;
use Sofascore\PurgatoryBundle\RouteParamValueResolver\EnumValuesResolver;
use Sofascore\PurgatoryBundle\RouteParamValueResolver\PropertyValuesResolver;
use Sofascore\PurgatoryBundle\RouteParamValueResolver\RawValuesResolver;
use Sofascore\PurgatoryBundle\RouteProvider\AbstractEntityRouteProvider;
use Sofascore\PurgatoryBundle\RouteProvider\PropertyAccess\PurgatoryPropertyAccessor;
use Sofascore\PurgatoryBundle\RouteProvider\PurgeRoute;
use Sofascore\PurgatoryBundle\RouteProvider\RemovedEntityRouteProvider;
use Sofascore\PurgatoryBundle\Tests\Fixtures\DummyStringEnum;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\PropertyAccess\PropertyAccess;

#[CoversClass(AbstractEntityRouteProvider::class)]
#[CoversClass(RemovedEntityRouteProvider::class)]
final class RemovedEntityRouteProviderTest extends TestCase
{
    public function testProvideRoutesToPurgeWithoutIf(): void
    {
        $routeProvider = $this->createRouteProvider([
            'stdClass' => [
                [
                    'routeName' => 'foo_route',
                ],
            ],
            'stdClass::foo' => [
                [
                    'routeName' => 'bar_route',
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
                ],
                [
                    'routeName' => 'qux_route',
                    'routeParams' => [
                        'param1' => [
                            'type' => PropertyValues::type(),
                            'values' => ['foo', 'bar'],
                        ],
                        'param2' => [
                            'type' => PropertyValues::type(),
                            'values' => ['qux'],
                            'optional' => true,
                        ],
                    ],
                ],
            ],
        ], false);

        $entity = new \stdClass();
        $entity->foo = 1;
        $entity->bar = 2;
        $entity->baz = 3;
        $entity->qux = null;

        self::assertTrue($routeProvider->supports(Action::Delete, $entity));
        self::assertFalse($routeProvider->supports(Action::Create, $entity));
        self::assertFalse($routeProvider->supports(Action::Update, $entity));

        $routes = [...$routeProvider->provideRoutesFor(Action::Delete, $entity, [])];

        self::assertCount(6, $routes);
        self::assertContainsOnlyInstancesOf(PurgeRoute::class, $routes);

        self::assertSame(['name' => 'foo_route', 'params' => []], (array) $routes[0]);
        self::assertSame(['name' => 'bar_route', 'params' => []], (array) $routes[1]);
        self::assertSame(['name' => 'baz_route', 'params' => ['param1' => 1, 'param2' => 3]], (array) $routes[2]);
        self::assertSame(['name' => 'baz_route', 'params' => ['param1' => 2, 'param2' => 3]], (array) $routes[3]);
        self::assertSame(['name' => 'qux_route', 'params' => ['param1' => 1, 'param2' => null]], (array) $routes[4]);
        self::assertSame(['name' => 'qux_route', 'params' => ['param1' => 2, 'param2' => null]], (array) $routes[5]);
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

        self::assertTrue($routeProvider->supports(Action::Delete, $entity));
        self::assertFalse($routeProvider->supports(Action::Create, $entity));
        self::assertFalse($routeProvider->supports(Action::Update, $entity));

        $routes = [...$routeProvider->provideRoutesFor(Action::Delete, $entity, [])];

        self::assertCount(2, $routes);
        self::assertContainsOnlyInstancesOf(PurgeRoute::class, $routes);

        self::assertSame(['name' => 'foo_route', 'params' => []], (array) $routes[0]);
        self::assertSame(['name' => 'bar_route', 'params' => []], (array) $routes[1]);
    }

    public function testExceptionIsThrownWhenEntityMetadataIsNotFound(): void
    {
        $configurationLoader = $this->createMock(ConfigurationLoaderInterface::class);
        $configurationLoader->method('load')
            ->willReturn(new Configuration([]));

        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $managerRegistry->method('getManagerForClass')
            ->with(\stdClass::class)
            ->willReturn(null);

        $routeProvider = new RemovedEntityRouteProvider(
            $configurationLoader,
            null,
            new ServiceLocator([]),
            $managerRegistry,
        );

        $this->expectException(EntityMetadataNotFoundException::class);

        iterator_to_array($routeProvider->provideRoutesFor(Action::Delete, new \stdClass(), []));
    }

    public function testExceptionIsThrownWhenIfIsUsedWithoutExpressionLangInstalled(): void
    {
        $routeProvider = $this->createRouteProvider([
            'stdClass' => [
                [
                    'routeName' => 'foo_route',
                    'if' => 'obj.test == true',
                ],
            ],
        ], false);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('You cannot use expressions because the Symfony ExpressionLanguage component is not installed.');

        iterator_to_array($routeProvider->provideRoutesFor(Action::Delete, new \stdClass(), []));
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

        self::assertTrue($routeProvider->supports(Action::Delete, $entity));
        self::assertFalse($routeProvider->supports(Action::Create, $entity));
        self::assertFalse($routeProvider->supports(Action::Update, $entity));

        $routes = [...$routeProvider->provideRoutesFor(Action::Delete, $entity, [])];

        self::assertCount(6, $routes);
        self::assertContainsOnlyInstancesOf(PurgeRoute::class, $routes);

        // RawValueResolver
        self::assertSame(['name' => 'foo_route', 'params' => ['foo' => 'foo']], (array) $routes[0]);
        self::assertSame(['name' => 'foo_route', 'params' => ['foo' => 1]], (array) $routes[1]);
        self::assertSame(['name' => 'foo_route', 'params' => ['foo' => null]], (array) $routes[2]);

        // EnumValueResolver
        self::assertSame(['name' => 'foo_route', 'params' => ['foo' => 'case1']], (array) $routes[3]);
        self::assertSame(['name' => 'foo_route', 'params' => ['foo' => 'case2']], (array) $routes[4]);
        self::assertSame(['name' => 'foo_route', 'params' => ['foo' => 'case3']], (array) $routes[5]);
    }

    private function createRouteProvider(array $configuration, bool $withExpressionLang): RemovedEntityRouteProvider
    {
        $configurationLoader = $this->createMock(ConfigurationLoaderInterface::class);
        $configurationLoader->method('load')
            ->willReturn(new Configuration($configuration));

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

        $expressionLanguage = null;
        if ($withExpressionLang) {
            $expressionLanguage = $this->createMock(ExpressionLanguage::class);
            $expressionLanguage->method('evaluate')
                ->willReturnOnConsecutiveCalls(true, true, false);
        }

        $propertyAccessor = new PurgatoryPropertyAccessor(PropertyAccess::createPropertyAccessor());

        $routeParamValueResolvers = [
            PropertyValues::type() => static fn () => new PropertyValuesResolver(new PurgatoryPropertyAccessor($propertyAccessor)),
            EnumValues::type() => static fn () => new EnumValuesResolver(),
            RawValues::type() => static fn () => new RawValuesResolver(),
        ];

        return new RemovedEntityRouteProvider(
            $configurationLoader,
            $expressionLanguage,
            new ServiceLocator($routeParamValueResolvers + [
                CompoundValues::type() => static fn () => new CompoundValuesResolver(new ServiceLocator($routeParamValueResolvers)),
            ]),
            $managerRegistry,
        );
    }
}
