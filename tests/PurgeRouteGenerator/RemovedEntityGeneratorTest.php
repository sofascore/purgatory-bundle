<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\PurgeRouteGenerator;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle2\Cache\Configuration\ConfigurationLoaderInterface;
use Sofascore\PurgatoryBundle2\Exception\LogicException;
use Sofascore\PurgatoryBundle2\Listener\Enum\Action;
use Sofascore\PurgatoryBundle2\PurgeRouteGenerator\RemovedEntityRouteGenerator;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

#[CoversClass(RemovedEntityRouteGenerator::class)]
final class RemovedEntityGeneratorTest extends TestCase
{
    public function testGenerateRoutesToPurgeWithoutIf(): void
    {
        $generator = $this->createGenerator([
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
                        'param1' => ['foo', 'bar'],
                        'param2' => ['baz'],
                    ],
                    'if' => null,
                ],
            ],
        ], false);

        $entity = new \stdClass();

        self::assertTrue($generator->supports(Action::Delete, new \stdClass()));
        self::assertFalse($generator->supports(Action::Create, new \stdClass()));
        self::assertFalse($generator->supports(Action::Update, new \stdClass()));

        $routes = [...$generator->getRoutesToPurge(Action::Delete, $entity, [])];

        self::assertCount(4, $routes);
        self::assertSame(['routeName' => 'foo_route', 'routeParams' => []], $routes[0]);
        self::assertSame(['routeName' => 'bar_route', 'routeParams' => []], $routes[1]);
        self::assertSame(['routeName' => 'baz_route', 'routeParams' => ['param1' => 1, 'param2' => 3]], $routes[2]);
        self::assertSame(['routeName' => 'baz_route', 'routeParams' => ['param1' => 2, 'param2' => 3]], $routes[3]);
    }

    public function testGenerateRoutesToPurgeWithIf(): void
    {
        $generator = $this->createGenerator([
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
                        'param1' => ['foo', 'bar'],
                        'param2' => ['baz'],
                    ],
                    'if' => 'obj.test == true',
                ],
            ],
        ], true);

        $entity = new \stdClass();

        self::assertTrue($generator->supports(Action::Delete, new \stdClass()));
        self::assertFalse($generator->supports(Action::Create, new \stdClass()));
        self::assertFalse($generator->supports(Action::Update, new \stdClass()));

        $routes = [...$generator->getRoutesToPurge(Action::Delete, $entity, [])];

        self::assertCount(2, $routes);
        self::assertSame(['routeName' => 'foo_route', 'routeParams' => []], $routes[0]);
        self::assertSame(['routeName' => 'bar_route', 'routeParams' => []], $routes[1]);
    }

    public function testExceptionIsThrownWhenIfIsUsedWithoutExpressionLangInstalled(): void
    {
        $generator = $this->createGenerator([
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

        iterator_to_array($generator->getRoutesToPurge(Action::Delete, $entity, []));
    }

    private function createGenerator(array $subscriptions, bool $withExpressionLang): RemovedEntityRouteGenerator
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

        return new RemovedEntityRouteGenerator(
            $configurationLoader,
            $propertyAccessor,
            $expressionLanguage,
            $managerRegistry,
        );
    }
}
