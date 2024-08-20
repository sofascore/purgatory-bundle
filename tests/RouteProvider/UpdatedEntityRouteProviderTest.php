<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\RouteProvider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\CompoundValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\EnumValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\PropertyValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\RawValues;
use Sofascore\PurgatoryBundle\Cache\Configuration\Configuration;
use Sofascore\PurgatoryBundle\Cache\Configuration\ConfigurationLoaderInterface;
use Sofascore\PurgatoryBundle\Exception\LogicException;
use Sofascore\PurgatoryBundle\Listener\Enum\Action;
use Sofascore\PurgatoryBundle\RouteParamValueResolver\CompoundValuesResolver;
use Sofascore\PurgatoryBundle\RouteParamValueResolver\EnumValuesResolver;
use Sofascore\PurgatoryBundle\RouteParamValueResolver\PropertyValuesResolver;
use Sofascore\PurgatoryBundle\RouteParamValueResolver\RawValuesResolver;
use Sofascore\PurgatoryBundle\RouteProvider\AbstractEntityRouteProvider;
use Sofascore\PurgatoryBundle\RouteProvider\PropertyAccess\PurgatoryPropertyAccessor;
use Sofascore\PurgatoryBundle\RouteProvider\PurgeRoute;
use Sofascore\PurgatoryBundle\RouteProvider\UpdatedEntityRouteProvider;
use Sofascore\PurgatoryBundle\Tests\Fixtures\DummyStringEnum;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyPath;

#[CoversClass(AbstractEntityRouteProvider::class)]
#[CoversClass(UpdatedEntityRouteProvider::class)]
final class UpdatedEntityRouteProviderTest extends TestCase
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
            ],
        ], false);

        $entity = new \stdClass();
        $entity->foo = 'new';
        $entity->bar = 2;
        $entity->baz = 3;

        self::assertTrue($routeProvider->supports(Action::Update, $entity));
        self::assertFalse($routeProvider->supports(Action::Delete, $entity));
        self::assertFalse($routeProvider->supports(Action::Create, $entity));

        $routes = [...$routeProvider->provideRoutesFor(
            action: Action::Update,
            entity: $entity,
            entityChangeSet: [
                'foo' => ['old', 'new'],
            ],
        )];

        self::assertCount(6, $routes);
        self::assertContainsOnlyInstancesOf(PurgeRoute::class, $routes);

        self::assertSame(['name' => 'foo_route', 'params' => []], (array) $routes[0]);
        self::assertSame(['name' => 'bar_route', 'params' => []], (array) $routes[1]);
        self::assertSame(['name' => 'baz_route', 'params' => ['param1' => 'new', 'param2' => 3]], (array) $routes[2]);
        self::assertSame(['name' => 'baz_route', 'params' => ['param1' => 'old', 'param2' => 3]], (array) $routes[3]);
        self::assertSame(['name' => 'baz_route', 'params' => ['param1' => 2, 'param2' => 3]], (array) $routes[4]);
        self::assertSame(['name' => 'baz_route', 'params' => ['param1' => 'old', 'param2' => 3]], (array) $routes[5]);
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

        self::assertTrue($routeProvider->supports(Action::Update, $entity));
        self::assertFalse($routeProvider->supports(Action::Delete, $entity));
        self::assertFalse($routeProvider->supports(Action::Create, $entity));

        $routes = [...$routeProvider->provideRoutesFor(
            action: Action::Update,
            entity: $entity,
            entityChangeSet: [
                'foo' => ['old', 'new'],
            ],
        )];

        self::assertCount(2, $routes);
        self::assertContainsOnlyInstancesOf(PurgeRoute::class, $routes);

        self::assertSame(['name' => 'foo_route', 'params' => []], (array) $routes[0]);
        self::assertSame(['name' => 'bar_route', 'params' => []], (array) $routes[1]);
    }

    #[RequiresMethod(PropertyPath::class, 'isNullSafe')]
    public function testProvideRoutesToPurgeWithOldValues(): void
    {
        $routeProvider = $this->createRouteProvider([
            'stdClass::foo' => [
                [
                    'routeName' => 'foo_route',
                    'routeParams' => [
                        'param1' => [
                            'type' => PropertyValues::type(),
                            'values' => ['foo'],
                        ],
                    ],
                ],
                [
                    'routeName' => 'missing_route_param',
                    'routeParams' => [
                        'param1' => [
                            'type' => PropertyValues::type(),
                            'values' => ['foo'],
                        ],
                        'param2' => [
                            'type' => PropertyValues::type(),
                            'values' => ['bar'],
                        ],
                    ],
                ],
            ],
            'stdClass::embeddable.qux' => [
                [
                    'routeName' => 'embeddable_route',
                    'routeParams' => [
                        'param1' => [
                            'type' => PropertyValues::type(),
                            'values' => ['embeddable?.qux'],
                        ],
                    ],
                ],
            ],
            'stdClass::association' => [
                [
                    'routeName' => 'association_route',
                    'routeParams' => [
                        'param1' => [
                            'type' => PropertyValues::type(),
                            'values' => ['association.corge'],
                        ],
                    ],
                ],
            ],
        ], false);

        $entity = new \stdClass();
        $entity->foo = 'new';
        $entity->bar = null;
        $entity->baz = 3;
        $entity->embeddable = null;

        $association = new \stdClass();
        $association->corge = null;
        $entity->association = $association;

        self::assertTrue($routeProvider->supports(Action::Update, $entity));
        self::assertFalse($routeProvider->supports(Action::Delete, $entity));
        self::assertFalse($routeProvider->supports(Action::Create, $entity));

        $oldAssociation = new \stdClass();
        $oldAssociation->corge = 5;

        $routes = [...$routeProvider->provideRoutesFor(
            action: Action::Update,
            entity: $entity,
            entityChangeSet: [
                'foo' => [['one', 'two'], 'new'],
                'embeddable.qux' => [4, null],
                'association' => [$oldAssociation, null],
            ],
        )];

        self::assertCount(5, $routes);
        self::assertContainsOnlyInstancesOf(PurgeRoute::class, $routes);

        self::assertSame(['name' => 'foo_route', 'params' => ['param1' => 'new']], (array) $routes[0]);
        self::assertSame(['name' => 'foo_route', 'params' => ['param1' => 'one']], (array) $routes[1]);
        self::assertSame(['name' => 'foo_route', 'params' => ['param1' => 'two']], (array) $routes[2]);
        self::assertSame(['name' => 'embeddable_route', 'params' => ['param1' => 4]], (array) $routes[3]);
        self::assertSame(['name' => 'association_route', 'params' => ['param1' => 5]], (array) $routes[4]);
    }

    public function testProvideRoutesToPurgeWithArrayAccess(): void
    {
        $routeProvider = $this->createRouteProvider([
            'stdClass::foo' => [
                [
                    'routeName' => 'foo_route',
                    'routeParams' => [
                        'param1' => [
                            'type' => PropertyValues::type(),
                            'values' => ['[foo]'],
                        ],
                    ],
                ],
            ],
        ], false);

        $entity = new class extends \stdClass implements \ArrayAccess {
            public function offsetExists(mixed $offset): bool
            {
                return property_exists($this, $offset);
            }

            public function offsetGet(mixed $offset): mixed
            {
                return $this->offsetExists($offset)
                    ? $this->{$offset}
                    : throw new \OutOfBoundsException(\sprintf('The property "%s" does not exist.', $offset));
            }

            public function offsetSet(mixed $offset, mixed $value): void
            {
                $this->{$offset} = $value;
            }

            public function offsetUnset(mixed $offset): void
            {
                unset($this->{$offset});
            }
        };
        $entity->foo = 'new';

        self::assertTrue($routeProvider->supports(Action::Update, $entity));
        self::assertFalse($routeProvider->supports(Action::Delete, $entity));
        self::assertFalse($routeProvider->supports(Action::Create, $entity));

        $routes = [...$routeProvider->provideRoutesFor(
            action: Action::Update,
            entity: $entity,
            entityChangeSet: [
                'foo' => ['old', 'new'],
            ],
        )];

        self::assertCount(1, $routes);
        self::assertContainsOnlyInstancesOf(PurgeRoute::class, $routes);

        self::assertSame(['name' => 'foo_route', 'params' => ['param1' => 'new']], (array) $routes[0]);
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

        $entity = new \stdClass();

        $this->expectException(LogicException::class);

        iterator_to_array($routeProvider->provideRoutesFor(Action::Delete, $entity, []));
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

        self::assertTrue($routeProvider->supports(Action::Update, $entity));
        self::assertFalse($routeProvider->supports(Action::Delete, $entity));
        self::assertFalse($routeProvider->supports(Action::Create, $entity));

        $routes = [...$routeProvider->provideRoutesFor(
            action: Action::Update,
            entity: $entity,
            entityChangeSet: [
                'foo' => ['old', 'new'],
            ],
        )];

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

    private function createRouteProvider(array $configuration, bool $withExpressionLang): UpdatedEntityRouteProvider
    {
        $configurationLoader = $this->createMock(ConfigurationLoaderInterface::class);
        $configurationLoader->method('load')
            ->willReturn(new Configuration($configuration));

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

        return new UpdatedEntityRouteProvider(
            $configurationLoader,
            $expressionLanguage,
            new ServiceLocator($routeParamValueResolvers + [
                CompoundValues::type() => static fn () => new CompoundValuesResolver(new ServiceLocator($routeParamValueResolvers)),
            ]),
            new PurgatoryPropertyAccessor($propertyAccessor),
        );
    }
}
