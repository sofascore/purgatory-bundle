<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Cache\Subscription;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresFunction;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Sofascore\PurgatoryBundle\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\ExpressionValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\PropertyValues;
use Sofascore\PurgatoryBundle\Attribute\Target\ForProperties;
use Sofascore\PurgatoryBundle\Cache\PropertyResolver\SubscriptionResolverInterface;
use Sofascore\PurgatoryBundle\Cache\RouteMetadata\RouteMetadata;
use Sofascore\PurgatoryBundle\Cache\RouteMetadata\RouteMetadataProviderInterface;
use Sofascore\PurgatoryBundle\Cache\Subscription\PurgeSubscription;
use Sofascore\PurgatoryBundle\Cache\Subscription\PurgeSubscriptionProvider;
use Sofascore\PurgatoryBundle\Cache\TargetResolver\TargetResolverInterface;
use Sofascore\PurgatoryBundle\Exception\EntityMetadataNotFoundException;
use Sofascore\PurgatoryBundle\Exception\InvalidIfClosureException;
use Sofascore\PurgatoryBundle\Exception\InvalidIfExpressionException;
use Sofascore\PurgatoryBundle\Tests\Cache\Subscription\Fixtures\DummyController;
use Sofascore\PurgatoryBundle\Tests\Cache\Subscription\Fixtures\DummyEntity;
use Sofascore\PurgatoryBundle\Tests\Cache\Subscription\Fixtures\DummyTarget;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Routing\Route;

#[CoversClass(PurgeSubscriptionProvider::class)]
final class PurgeSubscriptionProviderTest extends TestCase
{
    #[DataProvider('provideRouteMetadataWithoutTarget')]
    public function testWithoutTarget(RouteMetadata $routeMetadata, array $expectedSubscriptions): void
    {
        $routeMetadataProvider = self::createStub(RouteMetadataProviderInterface::class);
        $routeMetadataProvider->method('provide')
            ->willReturnCallback(static function () use ($routeMetadata) {
                yield $routeMetadata;
            });

        $targetResolverLocator = $this->createMock(ContainerInterface::class);
        $targetResolverLocator->expects(self::never())->method('get');

        $purgeSubscriptionProvider = new PurgeSubscriptionProvider(
            subscriptionResolvers: [],
            routeMetadataProviders: [$routeMetadataProvider],
            managerRegistry: self::createStub(ManagerRegistry::class),
            targetResolverLocator: $targetResolverLocator,
            expressionLanguage: null,
        );

        /** @var PurgeSubscription[] $propertySubscriptions */
        $propertySubscriptions = [...$purgeSubscriptionProvider->provide()];

        self::assertCount(\count($expectedSubscriptions), $propertySubscriptions);
        self::assertEquals($expectedSubscriptions, $propertySubscriptions);
    }

    public static function provideRouteMetadataWithoutTarget(): iterable
    {
        $route = new Route('/foo');
        yield 'PurgeOn for route without params' => [
            'routeMetadata' => new RouteMetadata(
                routeName: 'foo',
                route: $route,
                purgeOn: new PurgeOn(
                    class: 'FooEntity',
                ),
                reflectionMethod: new \ReflectionMethod(DummyController::class, 'barAction'),
            ),
            'expectedSubscriptions' => [
                new PurgeSubscription(
                    class: 'FooEntity',
                    property: null,
                    routeParams: [],
                    routeName: 'foo',
                    route: $route,
                    actions: null,
                    if: null,
                ),
            ],
        ];

        $route = new Route('/foo/{bar}');
        yield 'PurgeOn for route with params' => [
            'routeMetadata' => new RouteMetadata(
                routeName: 'foo',
                route: $route,
                purgeOn: new PurgeOn(
                    class: 'FooEntity',
                    routeParams: ['bar' => 'baz'],
                ),
                reflectionMethod: new \ReflectionMethod(DummyController::class, 'barAction'),
            ),
            'expectedSubscriptions' => [
                new PurgeSubscription(
                    class: 'FooEntity',
                    property: null,
                    routeParams: ['bar' => new PropertyValues('baz')],
                    routeName: 'foo',
                    route: $route,
                    actions: null,
                    if: null,
                ),
            ],
        ];

        $route = new Route('/foo/{bar}/{baz}');
        yield 'PurgeOn with automatic route params resolving' => [
            'routeMetadata' => new RouteMetadata(
                routeName: 'foo',
                route: $route,
                purgeOn: new PurgeOn(
                    class: 'FooEntity',
                ),
                reflectionMethod: new \ReflectionMethod(DummyController::class, 'barAction'),
            ),
            'expectedSubscriptions' => [
                new PurgeSubscription(
                    class: 'FooEntity',
                    property: null,
                    routeParams: ['bar' => new PropertyValues('bar'), 'baz' => new PropertyValues('baz')],
                    routeName: 'foo',
                    route: $route,
                    actions: null,
                    if: null,
                ),
            ],
        ];
    }

    #[DataProvider('provideRouteMetadataWithTarget')]
    public function testWithTarget(RouteMetadata $routeMetadata, array $targetResolverReturn, array $expectedSubscriptions): void
    {
        $subscriptionResolver = self::createStub(SubscriptionResolverInterface::class);
        $subscriptionResolver->method('resolveSubscription')
            ->willReturnCallback(static function () use ($expectedSubscriptions) {
                static $i = 0;

                yield $expectedSubscriptions[$i++];

                return true;
            });

        $routeMetadataProvider = self::createStub(RouteMetadataProviderInterface::class);
        $routeMetadataProvider->method('provide')
            ->willReturnCallback(static function () use ($routeMetadata) {
                yield $routeMetadata;
            });

        $classMetadata = self::createStub(ClassMetadata::class);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())
            ->method('getClassMetadata')
            ->with('FooEntity')
            ->willReturn($classMetadata);

        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $managerRegistry->expects(self::once())
            ->method('getManagerForClass')
            ->with('FooEntity')
            ->willReturn($entityManager);

        $dummyTargetResolver = $this->createMock(TargetResolverInterface::class);
        $dummyTargetResolver->expects(self::once())
            ->method('resolve')
            ->with($routeMetadata->purgeOn->target, $routeMetadata)
            ->willReturn($targetResolverReturn);

        $targetResolverLocator = $this->createMock(ContainerInterface::class);
        $targetResolverLocator->expects(self::once())
            ->method('get')
            ->with(DummyTarget::class)
            ->willReturn($dummyTargetResolver);

        $purgeSubscriptionProvider = new PurgeSubscriptionProvider(
            subscriptionResolvers: [$subscriptionResolver],
            routeMetadataProviders: [$routeMetadataProvider],
            managerRegistry: $managerRegistry,
            targetResolverLocator: $targetResolverLocator,
            expressionLanguage: null,
        );

        /** @var PurgeSubscription[] $propertySubscriptions */
        $propertySubscriptions = [...$purgeSubscriptionProvider->provide()];

        self::assertCount(\count($expectedSubscriptions), $propertySubscriptions);
        self::assertEquals($expectedSubscriptions, $propertySubscriptions);
    }

    public static function provideRouteMetadataWithTarget(): iterable
    {
        $route = new Route('/foo');
        yield 'PurgeOn for route without params' => [
            'routeMetadata' => new RouteMetadata(
                routeName: 'foo',
                route: $route,
                purgeOn: new PurgeOn(
                    class: 'FooEntity',
                    target: new DummyTarget(),
                ),
                reflectionMethod: new \ReflectionMethod(DummyController::class, 'barAction'),
            ),
            'targetResolverReturn' => ['foo', 'bar'],
            'expectedSubscriptions' => [
                new PurgeSubscription(
                    class: 'FooEntity',
                    property: 'foo',
                    routeParams: [],
                    routeName: 'foo',
                    route: $route,
                    actions: null,
                    if: null,
                ),
                new PurgeSubscription(
                    class: 'FooEntity',
                    property: 'bar',
                    routeParams: [],
                    routeName: 'bar',
                    route: $route,
                    actions: null,
                    if: null,
                ),
            ],
        ];

        $route = new Route('/foo/{bar}');
        yield 'PurgeOn for route with params' => [
            'routeMetadata' => new RouteMetadata(
                routeName: 'foo',
                route: $route,
                purgeOn: new PurgeOn(
                    class: 'FooEntity',
                    target: new DummyTarget(),
                    routeParams: ['bar' => 'baz'],
                ),
                reflectionMethod: new \ReflectionMethod(DummyController::class, 'barAction'),
            ),
            'targetResolverReturn' => ['bar', 'baz'],
            'expectedSubscriptions' => [
                new PurgeSubscription(
                    class: 'FooEntity',
                    property: 'bar',
                    routeParams: ['bar' => new PropertyValues('baz')],
                    routeName: 'foo',
                    route: $route,
                    actions: null,
                    if: null,
                ),
                new PurgeSubscription(
                    class: 'FooEntity',
                    property: 'baz',
                    routeParams: ['bar' => new PropertyValues('baz')],
                    routeName: 'bar',
                    route: $route,
                    actions: null,
                    if: null,
                ),
            ],
        ];

        $route = new Route('/foo/{bar}/{baz}');
        yield 'PurgeOn with automatic route params resolving' => [
            'routeMetadata' => new RouteMetadata(
                routeName: 'foo',
                route: $route,
                purgeOn: new PurgeOn(
                    class: 'FooEntity',
                    target: new DummyTarget(),
                ),
                reflectionMethod: new \ReflectionMethod(DummyController::class, 'barAction'),
            ),
            'targetResolverReturn' => ['qux', 'corge'],
            'expectedSubscriptions' => [
                new PurgeSubscription(
                    class: 'FooEntity',
                    property: 'qux',
                    routeParams: ['bar' => new PropertyValues('bar'), 'baz' => new PropertyValues('baz')],
                    routeName: 'foo',
                    route: $route,
                    actions: null,
                    if: null,
                ),
                new PurgeSubscription(
                    class: 'FooEntity',
                    property: 'corge',
                    routeParams: ['bar' => new PropertyValues('bar'), 'baz' => new PropertyValues('baz')],
                    routeName: 'foo',
                    route: $route,
                    actions: null,
                    if: null,
                ),
            ],
        ];
    }

    public function testExceptionIsThrownWhenEntityMetadataIsNotFound(): void
    {
        $routeMetadataProvider = self::createStub(RouteMetadataProviderInterface::class);
        $routeMetadataProvider->method('provide')
            ->willReturnCallback(static function () {
                yield new RouteMetadata(
                    routeName: 'foo',
                    route: new Route('/foo'),
                    purgeOn: new PurgeOn(
                        class: 'FooEntity',
                        target: new ForProperties(['bar']),
                    ),
                    reflectionMethod: new \ReflectionMethod(DummyController::class, 'barAction'),
                );
            });

        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $managerRegistry->expects(self::once())
            ->method('getManagerForClass')
            ->willReturn(null);

        $purgeSubscriptionProvider = new PurgeSubscriptionProvider(
            subscriptionResolvers: [],
            routeMetadataProviders: [$routeMetadataProvider],
            managerRegistry: $managerRegistry,
            targetResolverLocator: self::createStub(ContainerInterface::class),
            expressionLanguage: null,
        );

        $this->expectException(EntityMetadataNotFoundException::class);
        $this->expectExceptionMessage('Unable to retrieve metadata for entity "FooEntity".');

        [...$purgeSubscriptionProvider->provide()];
    }

    #[DataProvider('provideRouteMetadataWithMissingPurgeRouteParams')]
    public function testWithMissingRouteParams(
        RouteMetadata $routeMetadata,
        array $expectedMissingRequiredParameters,
    ): void {
        $routeMetadataProvider = self::createStub(RouteMetadataProviderInterface::class);
        $routeMetadataProvider->method('provide')
            ->willReturnCallback(static function () use ($routeMetadata) {
                yield $routeMetadata;
            });

        $purgeSubscriptionProvider = new PurgeSubscriptionProvider(
            subscriptionResolvers: [],
            routeMetadataProviders: [$routeMetadataProvider],
            managerRegistry: self::createStub(ManagerRegistry::class),
            targetResolverLocator: self::createStub(ContainerInterface::class),
            expressionLanguage: null,
        );

        $this->expectException(\LogicException::class);

        $missingParams = implode('", "', $expectedMissingRequiredParameters);
        $this->expectExceptionMessage(
            "Cannot purge route \"foo\" because the following required route parameters are missing: \"$missingParams\".",
        );

        [...$purgeSubscriptionProvider->provide()];
    }

    public static function provideRouteMetadataWithMissingPurgeRouteParams(): iterable
    {
        $route = new Route(
            path: '/{foo}',
            defaults: [],
        );
        yield [
            'routeMetadata' => new RouteMetadata(
                routeName: 'foo',
                route: $route,
                purgeOn: new PurgeOn(
                    class: 'FooEntity',
                    routeParams: [
                        'bar' => 'bar',
                    ],
                ),
                reflectionMethod: null,
            ),
            'expectedMissingRequiredParameters' => ['foo'],
        ];

        $route = new Route(
            path: '/{foo}/{bar}',
            defaults: [
                'bar' => 'bar',
            ],
        );
        yield [
            'routeMetadata' => new RouteMetadata(
                routeName: 'foo',
                route: $route,
                purgeOn: new PurgeOn(
                    class: 'FooEntity',
                    routeParams: [
                        'bar' => 'bar',
                    ],
                ),
                reflectionMethod: null,
            ),
            'expectedMissingRequiredParameters' => ['foo'],
        ];

        $route = new Route(
            path: '/{foo}/{bar}/{baz}',
            defaults: [
                'bar' => 'bar',
                'baz' => 'baz',
            ],
        );
        yield [
            'routeMetadata' => new RouteMetadata(
                routeName: 'foo',
                route: $route,
                purgeOn: new PurgeOn(
                    class: 'FooEntity',
                    routeParams: [
                        'baz' => 'baz',
                    ],
                ),
                reflectionMethod: null,
            ),
            'expectedMissingRequiredParameters' => ['foo'],
        ];

        $route = new Route(
            path: '/{foo}/{bar}/{baz}',
            defaults: [
                'baz' => 'baz',
            ],
        );
        yield [
            'routeMetadata' => new RouteMetadata(
                routeName: 'foo',
                route: $route,
                purgeOn: new PurgeOn(
                    class: 'FooEntity',
                    routeParams: [
                        'foo' => 'foo',
                    ],
                ),
                reflectionMethod: null,
            ),
            'expectedMissingRequiredParameters' => ['bar'],
        ];

        $route = new Route(
            path: '/{foo}/{bar}/{baz}',
            defaults: [
                'baz' => 'baz',
            ],
        );
        yield [
            'routeMetadata' => new RouteMetadata(
                routeName: 'foo',
                route: $route,
                purgeOn: new PurgeOn(
                    class: 'FooEntity',
                    routeParams: [
                        'qux' => 'qux',
                    ],
                ),
                reflectionMethod: null,
            ),
            'expectedMissingRequiredParameters' => ['foo', 'bar'],
        ];

        $route = new Route(
            path: '/{foo}/{bar}/{baz}',
            defaults: [],
        );
        yield [
            'routeMetadata' => new RouteMetadata(
                routeName: 'foo',
                route: $route,
                purgeOn: new PurgeOn(
                    class: 'FooEntity',
                    routeParams: [
                        'bar' => 'bar',
                    ],
                ),
                reflectionMethod: null,
            ),
            'expectedMissingRequiredParameters' => ['foo', 'baz'],
        ];
    }

    #[DataProvider('provideInvalidExpressions')]
    public function testExceptionIsThrownOnInvalidRouteParamsExpression(string $expression, string $expectedMessage): void
    {
        $routeMetadataProvider = self::createStub(RouteMetadataProviderInterface::class);
        $routeMetadataProvider->method('provide')
            ->willReturnCallback(static function () use ($expression): iterable {
                yield new RouteMetadata(
                    routeName: 'foo',
                    route: new Route('/{foo}'),
                    purgeOn: new PurgeOn(
                        class: 'FooEntity',
                        routeParams: ['foo' => new ExpressionValues($expression)],
                    ),
                    reflectionMethod: null,
                );
            });

        $purgeSubscriptionProvider = new PurgeSubscriptionProvider(
            subscriptionResolvers: [],
            routeMetadataProviders: [$routeMetadataProvider],
            managerRegistry: self::createStub(ManagerRegistry::class),
            targetResolverLocator: self::createStub(ContainerInterface::class),
            expressionLanguage: new ExpressionLanguage(
                providers: [
                    new class implements ExpressionFunctionProviderInterface {
                        public function getFunctions(): array
                        {
                            return [
                                new ExpressionFunction('valid_function', static function () {}, static function () {}),
                            ];
                        }
                    },
                ],
            ),
        );

        $this->expectException(InvalidIfExpressionException::class);
        $this->expectExceptionMessage($expectedMessage);

        [...$purgeSubscriptionProvider->provide()];
    }

    #[DataProvider('provideInvalidExpressions')]
    public function testExceptionIsThrownOnInvalidIfExpression(string $if, string $expectedMessage): void
    {
        $routeMetadataProvider = self::createStub(RouteMetadataProviderInterface::class);
        $routeMetadataProvider->method('provide')
            ->willReturnCallback(static function () use ($if): iterable {
                yield new RouteMetadata(
                    routeName: 'foo',
                    route: new Route('/{foo}'),
                    purgeOn: new PurgeOn(
                        class: 'FooEntity',
                        if: $if,
                    ),
                    reflectionMethod: null,
                );
            });

        $purgeSubscriptionProvider = new PurgeSubscriptionProvider(
            subscriptionResolvers: [],
            routeMetadataProviders: [$routeMetadataProvider],
            managerRegistry: self::createStub(ManagerRegistry::class),
            targetResolverLocator: self::createStub(ContainerInterface::class),
            expressionLanguage: new ExpressionLanguage(
                providers: [
                    new class implements ExpressionFunctionProviderInterface {
                        public function getFunctions(): array
                        {
                            return [
                                new ExpressionFunction('valid_function', static function () {}, static function () {}),
                            ];
                        }
                    },
                ],
            ),
        );

        $this->expectException(InvalidIfExpressionException::class);
        $this->expectExceptionMessage($expectedMessage);

        [...$purgeSubscriptionProvider->provide()];
    }

    public static function provideInvalidExpressions(): iterable
    {
        yield [
            'invalidObj.getMethod()',
            'Invalid "if" expression provided for route "foo": "Variable "invalidObj" is not valid around position 1 for expression `invalidObj.getMethod()`."',
        ];
        yield [
            'entity !== null',
            'Invalid "if" expression provided for route "foo": "Variable "entity" is not valid around position 1 for expression `entity !== null`."',
        ];
        yield [
            'some_function(obj)',
            'Invalid "if" expression provided for route "foo": "The function "some_function" does not exist around position 1 for expression `some_function(obj)`."',
        ];
        yield [
            'valid_function(author)',
            'Invalid "if" expression provided for route "foo": "Variable "author" is not valid around position 16 for expression `valid_function(author)`."',
        ];
    }

    #[DataProvider('providerRouteMetadataWithPhp85Features')]
    public function testWithClosures(RouteMetadata $routeMetadata, array $expectedSubscriptions): void
    {
        $routeMetadataProvider = $this->createMock(RouteMetadataProviderInterface::class);
        $routeMetadataProvider->expects(self::once())
            ->method('provide')
            ->willReturnCallback(static function () use ($routeMetadata) {
                yield $routeMetadata;
            });

        $targetResolverLocator = $this->createMock(ContainerInterface::class);
        $targetResolverLocator->expects(self::never())->method('get');

        $purgeSubscriptionProvider = new PurgeSubscriptionProvider(
            subscriptionResolvers: [],
            routeMetadataProviders: [$routeMetadataProvider],
            managerRegistry: self::createStub(ManagerRegistry::class),
            targetResolverLocator: $targetResolverLocator,
            expressionLanguage: null,
        );

        /** @var PurgeSubscription[] $propertySubscriptions */
        $propertySubscriptions = [...$purgeSubscriptionProvider->provide()];

        self::assertCount(\count($expectedSubscriptions), $propertySubscriptions);
        self::assertEquals($expectedSubscriptions, $propertySubscriptions);
    }

    public static function providerRouteMetadataWithPhp85Features(): iterable
    {
        $route = new Route('/foo');
        $if = static function (DummyEntity $entity): bool {
            return $entity->getData() > 0;
        };

        yield 'PurgeOn with closure' => [
            'routeMetadata' => new RouteMetadata(
                routeName: 'foo',
                route: $route,
                purgeOn: new PurgeOn(
                    class: DummyEntity::class,
                    if: $if,
                ),
                reflectionMethod: new \ReflectionMethod(DummyController::class, 'barAction'),
            ),
            'expectedSubscriptions' => [
                new PurgeSubscription(
                    class: DummyEntity::class,
                    property: null,
                    routeParams: [],
                    routeName: 'foo',
                    route: $route,
                    actions: null,
                    if: $if,
                ),
            ],
        ];
    }

    #[RequiresFunction('deepclone_to_array')]
    #[DataProvider('provideInvalidClosures')]
    public function testInvalidClosures(\Closure $if, string $expectedMessage): void
    {
        $routeMetadataProvider = $this->createMock(RouteMetadataProviderInterface::class);
        $routeMetadataProvider->expects(self::once())
            ->method('provide')
            ->willReturnCallback(static function () use ($if): iterable {
                yield new RouteMetadata(
                    routeName: 'foo',
                    route: new Route('/{foo}'),
                    purgeOn: new PurgeOn(
                        class: DummyEntity::class,
                        if: $if,
                    ),
                    reflectionMethod: null,
                );
            });

        $purgeSubscriptionProvider = new PurgeSubscriptionProvider(
            subscriptionResolvers: [],
            routeMetadataProviders: [$routeMetadataProvider],
            managerRegistry: self::createStub(ManagerRegistry::class),
            targetResolverLocator: self::createStub(ContainerInterface::class),
            expressionLanguage: self::createStub(ExpressionLanguage::class),
        );

        $this->expectException(InvalidIfClosureException::class);
        $this->expectExceptionMessage($expectedMessage);

        [...$purgeSubscriptionProvider->provide()];
    }

    public static function provideInvalidClosures(): iterable
    {
        yield 'invalid return type (union)' => [
            'if' => static function (DummyEntity $entity): int|string {
                return $entity->getData();
            },
            'expectedMessage' => 'Return type must be bool',
        ];

        yield 'nullable return type' => [
            'if' => static function (DummyEntity $entity): ?bool {
                return null;
            },
            'expectedMessage' => 'Return type must be bool',
        ];

        yield 'invalid return type' => [
            'if' => static function (DummyEntity $entity): int {
                return $entity->getData();
            },
            'expectedMessage' => 'Return type must be bool',
        ];

        yield 'too many parameters' => [
            'if' => static function (DummyEntity $entity, array $options): bool {
                return $entity->getData() > 0;
            },
            'expectedMessage' => 'Closure must have exactly 1 parameter',
        ];

        yield 'invalid parameter type (union)' => [
            'if' => static function (DummyEntity|int $entity): bool {
                return $entity->getData() > 0;
            },
            'expectedMessage' => 'Parameter in closure must be of type '.DummyEntity::class,
        ];

        yield 'nullable parameter type' => [
            'if' => static function (?DummyEntity $entity): bool {
                return $entity?->getData() > 0;
            },
            'expectedMessage' => 'Parameter in closure must be of type '.DummyEntity::class,
        ];

        yield 'invalid parameter type' => [
            'if' => static function (\stdClass $entity): bool {
                return true;
            },
            'expectedMessage' => 'Parameter in closure must be of type '.DummyEntity::class,
        ];

        yield 'closure bound to an instance' => [
            'if' => (new class {
                public function getIf(): \Closure
                {
                    return function (DummyEntity $entity): bool {
                        return $this instanceof self;
                    };
                }
            })->getIf(),
            'expectedMessage' => 'Closure must be static',
        ];

        $number = 1;
        yield 'captured scalar variable' => [
            'if' => static function (DummyEntity $entity) use ($number): bool {
                return $entity->getData() > $number;
            },
            'expectedMessage' => 'Closure must not capture variables',
        ];

        $object = new DummyEntity();
        yield 'captured object variable' => [
            'if' => static function (DummyEntity $entity) use ($object): bool {
                return $entity->getData() > $object->getData();
            },
            'expectedMessage' => 'Closure must not capture variables',
        ];
    }
}
