<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Cache\Subscription;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresFunction;
use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Sofascore\PurgatoryBundle\Attribute\PurgeOn;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\CompoundValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\DynamicValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\ExpressionValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\PropertyValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\RawValues;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\ValuesInterface;
use Sofascore\PurgatoryBundle\Attribute\Target\ForProperties;
use Sofascore\PurgatoryBundle\Cache\PropertyResolver\SubscriptionResolverInterface;
use Sofascore\PurgatoryBundle\Cache\RouteMetadata\RouteMetadata;
use Sofascore\PurgatoryBundle\Cache\RouteMetadata\RouteMetadataProviderInterface;
use Sofascore\PurgatoryBundle\Cache\Subscription\PurgeSubscription;
use Sofascore\PurgatoryBundle\Cache\Subscription\PurgeSubscriptionProvider;
use Sofascore\PurgatoryBundle\Cache\TargetResolver\TargetResolverInterface;
use Sofascore\PurgatoryBundle\Exception\EntityMetadataNotFoundException;
use Sofascore\PurgatoryBundle\Exception\InvalidDynamicValuesClosureException;
use Sofascore\PurgatoryBundle\Exception\InvalidIfCallableException;
use Sofascore\PurgatoryBundle\Exception\InvalidIfExpressionException;
use Sofascore\PurgatoryBundle\Tests\Cache\Subscription\Fixtures\DummyController;
use Sofascore\PurgatoryBundle\Tests\Cache\Subscription\Fixtures\DummyEntity;
use Sofascore\PurgatoryBundle\Tests\Cache\Subscription\Fixtures\DummyTarget;
use Sofascore\PurgatoryBundle\Tests\Fixtures\IfCallables;
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

    #[RequiresPhp('>= 8.5.0')]
    #[DataProvider('provideValidDynamicValuesClosures')]
    public function testValidDynamicValuesClosure(\Closure $provider): void
    {
        $values = new DynamicValues($provider);

        /** @var PurgeSubscription[] $subscriptions */
        $subscriptions = [...$this->createProviderForRouteParams(['foo' => $values])->provide()];

        self::assertCount(1, $subscriptions);
        self::assertSame($values, $subscriptions[0]->routeParams['foo']);
    }

    public static function provideValidDynamicValuesClosures(): iterable
    {
        yield 'no parameters' => [static fn (): array => []];
        yield 'one parameter' => [static fn (object $entity): array => []];
        yield 'optional second parameter' => [static fn (object $entity, int $limit = 10): array => []];
    }

    #[RequiresPhp('>= 8.5.0')]
    #[DataProvider('provideInvalidDynamicValuesClosures')]
    public function testInvalidDynamicValuesClosures(ValuesInterface $values, string $expectedMessage): void
    {
        $this->expectException(InvalidDynamicValuesClosureException::class);
        $this->expectExceptionMessage('Invalid "DynamicValues" closure provided for route "foo": "'.$expectedMessage.'"');

        [...$this->createProviderForRouteParams(['foo' => $values])->provide()];
    }

    public static function provideInvalidDynamicValuesClosures(): iterable
    {
        $bound = (new class {
            public function getProvider(): \Closure
            {
                return function (object $entity): array {
                    return [$this];
                };
            }
        })->getProvider();

        yield 'closure bound to an instance' => [new DynamicValues($bound), 'The closure must be static.'];

        $number = 1;
        yield 'captured variable' => [
            new DynamicValues(static fn (object $entity): array => [$number]),
            'The closure must not capture variables.',
        ];

        yield 'first-class callable' => [
            new DynamicValues(strlen(...)),
            'First-class callables are not supported, use a static closure instead.',
        ];

        yield 'nested in compound values' => [
            new CompoundValues(new RawValues(1), new DynamicValues(strlen(...))),
            'First-class callables are not supported, use a static closure instead.',
        ];

        yield 'two required parameters' => [
            new DynamicValues(static fn (object $entity, int $limit): array => []),
            'The closure must not require more than one parameter.',
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

    public function testExceptionIsThrownOnInvalidNestedRouteParamsExpression(): void
    {
        $routeMetadataProvider = self::createStub(RouteMetadataProviderInterface::class);
        $routeMetadataProvider->method('provide')
            ->willReturnCallback(static function (): iterable {
                yield new RouteMetadata(
                    routeName: 'foo',
                    route: new Route('/{foo}'),
                    purgeOn: new PurgeOn(
                        class: 'FooEntity',
                        routeParams: ['foo' => new CompoundValues(new RawValues(1), new ExpressionValues('invalidObj.getMethod()'))],
                    ),
                    reflectionMethod: null,
                );
            });

        $purgeSubscriptionProvider = new PurgeSubscriptionProvider(
            subscriptionResolvers: [],
            routeMetadataProviders: [$routeMetadataProvider],
            managerRegistry: self::createStub(ManagerRegistry::class),
            targetResolverLocator: self::createStub(ContainerInterface::class),
            expressionLanguage: new ExpressionLanguage(),
        );

        $this->expectException(InvalidIfExpressionException::class);
        $this->expectExceptionMessage('Variable "invalidObj" is not valid around position 1 for expression `invalidObj.getMethod()`.');

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

    public function testIfCallable(): void
    {
        /** @var PurgeSubscription[] $subscriptions */
        $subscriptions = [...$this->createProviderForIf([IfCallables::class, 'isTrue'])->provide()];

        self::assertCount(1, $subscriptions);
        self::assertSame([IfCallables::class, 'isTrue'], $subscriptions[0]->if);
    }

    #[TestWith(['returnsInt', 'The method must declare a non-nullable bool return type.'])]
    #[TestWith(['takesTwo', 'The method must have exactly one parameter.'])]
    #[TestWith(['takesWrongType', 'The method parameter must be typed as "stdClass" or one of its parent types.'])]
    public function testInvalidIfCallables(string $method, string $expectedMessage): void
    {
        $purgeSubscriptionProvider = $this->createProviderForIf([IfCallables::class, $method]);

        $this->expectException(InvalidIfCallableException::class);
        $this->expectExceptionMessage('Invalid "if" callable provided for route "foo": "'.$expectedMessage.'"');

        [...$purgeSubscriptionProvider->provide()];
    }

    #[RequiresPhp('>= 8.5.0')]
    #[DataProvider('provideIfClosures')]
    public function testIfClosures(RouteMetadata $routeMetadata, array $expectedSubscriptions): void
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

        foreach ($expectedSubscriptions as $i => $expectedSubscription) {
            // closures can't be compared for equality, only for identity
            self::assertSame($expectedSubscription->if, $propertySubscriptions[$i]->if);
            self::assertEquals(
                [...get_object_vars($expectedSubscription), 'if' => null],
                [...get_object_vars($propertySubscriptions[$i]), 'if' => null],
            );
        }
    }

    public static function provideIfClosures(): iterable
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

    #[RequiresPhp('>= 8.5.0')]
    #[RequiresFunction('deepclone_to_array')]
    #[DataProvider('provideInvalidIfClosures')]
    public function testInvalidIfClosures(\Closure $if, string $expectedMessage): void
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

        $this->expectException(InvalidIfCallableException::class);
        $this->expectExceptionMessage('Invalid "if" callable provided for route "foo": "'.$expectedMessage.'"');

        [...$purgeSubscriptionProvider->provide()];
    }

    public static function provideInvalidIfClosures(): iterable
    {
        yield 'invalid return type (union)' => [
            'if' => static function (DummyEntity $entity): int|string {
                return $entity->getData();
            },
            'expectedMessage' => 'The closure must declare a non-nullable bool return type.',
        ];

        yield 'nullable return type' => [
            'if' => static function (DummyEntity $entity): ?bool {
                return null;
            },
            'expectedMessage' => 'The closure must declare a non-nullable bool return type.',
        ];

        yield 'invalid return type' => [
            'if' => static function (DummyEntity $entity): int {
                return $entity->getData();
            },
            'expectedMessage' => 'The closure must declare a non-nullable bool return type.',
        ];

        yield 'too many parameters' => [
            'if' => static function (DummyEntity $entity, array $options): bool {
                return $entity->getData() > 0;
            },
            'expectedMessage' => 'The closure must have exactly one parameter.',
        ];

        yield 'invalid parameter type (union)' => [
            'if' => static function (DummyEntity|int $entity): bool {
                return $entity->getData() > 0;
            },
            'expectedMessage' => 'The closure parameter must be typed as "'.DummyEntity::class.'" or one of its parent types.',
        ];

        yield 'nullable parameter type' => [
            'if' => static function (?DummyEntity $entity): bool {
                return $entity?->getData() > 0;
            },
            'expectedMessage' => 'The closure parameter must be typed as "'.DummyEntity::class.'" or one of its parent types.',
        ];

        yield 'invalid parameter type' => [
            'if' => static function (\stdClass $entity): bool {
                return true;
            },
            'expectedMessage' => 'The closure parameter must be typed as "'.DummyEntity::class.'" or one of its parent types.',
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
            'expectedMessage' => 'The closure must be static.',
        ];

        $number = 1;
        yield 'captured scalar variable' => [
            'if' => static function (DummyEntity $entity) use ($number): bool {
                return $entity->getData() > $number;
            },
            'expectedMessage' => 'The closure must not capture variables.',
        ];

        $object = new DummyEntity();
        yield 'captured object variable' => [
            'if' => static function (DummyEntity $entity) use ($object): bool {
                return $entity->getData() > $object->getData();
            },
            'expectedMessage' => 'The closure must not capture variables.',
        ];

        yield 'first-class callable of a static method' => [
            'if' => DummyEntity::isValid(...),
            'expectedMessage' => 'First-class callables are not supported, use a static closure instead.',
        ];

        yield 'first-class callable of a function' => [
            'if' => is_object(...),
            'expectedMessage' => 'First-class callables are not supported, use a static closure instead.',
        ];
    }

    /**
     * @param non-empty-array<string, ValuesInterface> $routeParams
     */
    private function createProviderForRouteParams(array $routeParams, ?ExpressionLanguage $expressionLanguage = null): PurgeSubscriptionProvider
    {
        $routeMetadataProvider = self::createStub(RouteMetadataProviderInterface::class);
        $routeMetadataProvider->method('provide')
            ->willReturnCallback(static function () use ($routeParams): iterable {
                yield new RouteMetadata(
                    routeName: 'foo',
                    route: new Route('/{foo}'),
                    purgeOn: new PurgeOn(
                        class: \stdClass::class,
                        routeParams: $routeParams,
                    ),
                    reflectionMethod: null,
                );
            });

        return new PurgeSubscriptionProvider(
            subscriptionResolvers: [],
            routeMetadataProviders: [$routeMetadataProvider],
            managerRegistry: self::createStub(ManagerRegistry::class),
            targetResolverLocator: self::createStub(ContainerInterface::class),
            expressionLanguage: $expressionLanguage,
        );
    }

    /**
     * @param callable-array<string> $if
     */
    private function createProviderForIf(array $if): PurgeSubscriptionProvider
    {
        $routeMetadataProvider = $this->createMock(RouteMetadataProviderInterface::class);
        $routeMetadataProvider->expects(self::once())
            ->method('provide')
            ->willReturnCallback(static function () use ($if): iterable {
                yield new RouteMetadata(
                    routeName: 'foo',
                    route: new Route('/foo'),
                    purgeOn: new PurgeOn(
                        class: \stdClass::class,
                        if: $if,
                    ),
                    reflectionMethod: null,
                );
            });

        return new PurgeSubscriptionProvider(
            subscriptionResolvers: [],
            routeMetadataProviders: [$routeMetadataProvider],
            managerRegistry: self::createStub(ManagerRegistry::class),
            targetResolverLocator: self::createStub(ContainerInterface::class),
            expressionLanguage: null,
        );
    }
}
