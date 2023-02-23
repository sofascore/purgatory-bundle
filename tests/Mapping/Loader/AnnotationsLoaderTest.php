<?php

namespace Sofascore\PurgatoryBundle\Tests\Mapping\Loader;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Annotation\PurgeOn;
use Sofascore\PurgatoryBundle\AnnotationReader\Reader;
use Sofascore\PurgatoryBundle\Mapping\Loader\AnnotationsLoader;
use Sofascore\PurgatoryBundle\Mapping\Loader\Configuration;
use Sofascore\PurgatoryBundle\Mapping\MappingCollection;
use Sofascore\PurgatoryBundle\Mapping\PropertySubscription;
use Sofascore\PurgatoryBundle\Tests\AnnotationReader\Fixtures\Entity1;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertEquals;

/**
 * @covers \Sofascore\PurgatoryBundle\Mapping\Loader\AnnotationsLoader
 * @covers \Sofascore\PurgatoryBundle\Mapping\MappingCollection
 * @covers \Sofascore\PurgatoryBundle\Mapping\MappingValue
 * @covers \Sofascore\PurgatoryBundle\Mapping\PropertySubscription
 */
class AnnotationsLoaderTest extends TestCase
{
    private const TEST_CONTROLLER = 'App\\Controller\\TestController';
    private RouteCollection $routeCollection;

    public static function mockCallable(): void
    {
    }

    public function testLoadOnUnconfiguredCacheDirAttemptsMappingsLoad(): void
    {
        $mocks = [
            $configurationMock,
            $routerMock,
            $controllerResolverMock,
            $readerMock,
            $objectManagerMock
        ] = $this->getMocks();

        $configurationMock->expects(self::once())->method('getCacheDir')->willReturn(null);

        $loader = new AnnotationsLoader(...$mocks);
        $loader->load();
    }

    public function testLoadOnConfiguredCacheDirSavesMappingsToCacheNoPropertySubscription()
    {
        $this->addRouteToCollection('app_api_v1_sport_list', '/api/v1/sport/list', self::TEST_CONTROLLER);

        $mocks = [
            $configurationMock,
            $routerMock,
            $controllerResolverMock,
            $readerMock,
            $objectManagerMock
        ] = $this->getMocks();

        self::assertDirectoryDoesNotExist('./purgatory');

        $configurationMock->expects(self::exactly(2))->method('getCacheDir')->willReturn('.');
        $configurationMock->expects(self::once())->method('getDebug')->willReturn(true);

        $readerMock->method('getAnnotations')->willReturn(
            [PurgeOn::class => [new PurgeOn(['value' => Entity1::class])]]
        );
        $objectManagerMock->method('getClassMetadata')->willReturn($this->mockClassMetadata(Entity1::class));

        $loader = new AnnotationsLoader(...$mocks);
        $loader->load();

        self::assertDirectoryExists('./purgatory');
        /** @var MappingCollection $result */
        $result = require './purgatory/mappings/collection.php';
        self::assertNotEmpty($result);
        assertCount(1, $result);
        assertEquals('app_api_v1_sport_list', $result->get('\\'.Entity1::class)[0]->getRouteName());
    }

    public function testLoadOnConfiguredCacheDirSavesMappingsToCache()
    {
        $this->addRouteToCollection('app_api_v1_sport_list', '/api/v1/sport/list', self::TEST_CONTROLLER);

        $mocks = [
            $configurationMock,
            $routerMock,
            $controllerResolverMock,
            $readerMock,
            $objectManagerMock
        ] = $this->getMocks();

        self::assertDirectoryDoesNotExist('./purgatory');

        $configurationMock->expects(self::exactly(2))->method('getCacheDir')->willReturn('.');
        $configurationMock->expects(self::once())->method('getDebug')->willReturn(true);

        $readerMock->method('getAnnotations')->willReturn(
            [PurgeOn::class => [new PurgeOn(['value' => Entity1::class, 'properties' => ['name']])]]
        );
        $objectManagerMock->method('getClassMetadata')->willReturn(
            $this->mockClassMetadata(Entity1::class, ['name', 'id', 'createdAt'])
        );

        $loader = new AnnotationsLoader(...$mocks);
        $loader->load();

        self::assertDirectoryExists('./purgatory');
        /** @var MappingCollection $result */
        $result = require './purgatory/mappings/collection.php';
        self::assertNotEmpty($result);
        assertCount(1, $result);
        assertEquals('app_api_v1_sport_list', $result->get(sprintf('\\%s::name', Entity1::class))[0]->getRouteName());
    }

    public function testCreatingSubscriptionFromAnnotation(): void
    {
        $testClass = 'Sofascore\\PurgatoryBundle\\SomeEntity';
        $testProperties = ['propertea1', 'propertea2'];
        $testRouteName = 'test_route';
        $testRoute = new Route('/api/v1/test/route', ['_controller' => self::TEST_CONTROLLER]);

        /** @var PropertySubscription[] $subscriptions */
        $subscriptions = [];
        $sample = new PurgeOn(
            $testClass,
            $testProperties,
            ['entityId' => 'id']
        );

        $annotationsLoader = new AnnotationsLoader(...$this->getMocks());
        $annotationsLoader->parsePurgeOn(
            $sample,
            $testRouteName,
            $testRoute,
            $subscriptions
        );

        self::assertCount(2, $subscriptions);
        self::assertInstanceOf(PropertySubscription::class, $subscriptions[0]);
        self::assertInstanceOf(PropertySubscription::class, $subscriptions[1]);
        self::assertEquals($testClass, $subscriptions[0]->getClass());
        self::assertEquals($testClass, $subscriptions[1]->getClass());
        self::assertEquals($testProperties[0], $subscriptions[0]->getProperty());
        self::assertEquals($testProperties[1], $subscriptions[1]->getProperty());
        $expArr = [
            'entityId' => ['id']
        ];
        self::assertEquals($expArr, $subscriptions[0]->getParameters());
        self::assertEquals($expArr, $subscriptions[1]->getParameters());
        self::assertEquals($testRouteName, $subscriptions[0]->getRouteName());
        self::assertEquals($testRouteName, $subscriptions[1]->getRouteName());
        self::assertEquals($testRoute->getPath(), $subscriptions[0]->getRoute()->getPath());
        self::assertEquals($testRoute->getPath(), $subscriptions[1]->getRoute()->getPath());
    }

    /**
     * @throws \ReflectionException|\Exception
     */
    public function testResolveSubscriptionsWithEmbeddedProperties(): void
    {
        $testClass = Entity1::class;
        $testProperty = 'propeteeh';
        $testRouteName = 'test_route';
        $testRoute = new Route('/api/v1/test/route', ['_controller' => self::TEST_CONTROLLER]);

        $subscription = new PropertySubscription($testClass, $testProperty);
        $subscription->setParameters(['entityId' => ['id']]);
        $subscription->setRoute($testRoute);
        $subscription->setRouteName($testRouteName);

        $mocks = [
            $configurationMock,
            $routerMock,
            $controllerResolverMock,
            $readerMock,
            $objectManagerMock
        ] = $this->getMocks();

        $mockedMetadata = $this->mockClassMetadata(
            $testClass,
            ['name', 'id', 'createdAt'],
            [$testProperty]
        );
        $mockedMetadata->method('getAssociationMappedByTargetField')->willReturn('name');
        $mockedMetadata->method('getAssociationTargetClass')->willReturn($testClass);

        $objectManagerMock->method('getClassMetadata')->willReturn(
            $mockedMetadata
        );

        $annotationsLoader = new AnnotationsLoader(...$mocks);

        $output = $annotationsLoader->resolveSubscriptions([$subscription]);

        self::assertCount(1, $output);
        $outputSubscription = $output[0];
        self::assertInstanceOf(PropertySubscription::class, $outputSubscription);
        self::assertEquals($testClass, $outputSubscription->getClass());
        self::assertNull($outputSubscription->getProperty());
        self::assertEquals(['entityId' => ['name.id']], $outputSubscription->getParameters());
        self::assertEquals($testRouteName, $outputSubscription->getRouteName());
        self::assertEquals($testRoute->getPath(), $outputSubscription->getRoute()->getPath());
    }

    protected function setUp(): void
    {
        if (file_exists('./purgatory/mappings/collection.php')) {
            unlink('./purgatory/mappings/collection.php');
        }
        if (file_exists('./purgatory/mappings/collection.php.meta')) {
            unlink('./purgatory/mappings/collection.php.meta');
        }
        if (is_dir('./purgatory/mappings')) {
            rmdir('./purgatory/mappings');
        }
        if (is_dir('./purgatory')) {
            rmdir('./purgatory');
        }

        $this->routeCollection = new RouteCollection();
    }

    /**
     * @return MockObject[]
     */
    private function getMocks(): array
    {
        $routerMock = $this->createMock(RouterInterface::class);
        $routerMock->method('getRouteCollection')->willReturn($this->routeCollection);

        $controllerResolverMock = $this->createMock(ControllerResolverInterface::class);
        $controllerResolverMock->method('getController')->willReturn(
            [self::class, 'mockCallable']
        );

        return [
            $this->createMock(Configuration::class),
            $routerMock,
            $controllerResolverMock,
            $this->createMock(Reader::class),
            $this->createMock(ObjectManager::class)
        ];
    }

    private function getRouteCollection(): RouteCollection
    {
        $collection = new RouteCollection();
        $collection->add(
            'app_api_v1_sport_list',
            new Route('/api/v1/sport/list', ['_controller' => self::TEST_CONTROLLER])
        );
        return $collection;
    }

    private function addRouteToCollection(string $routeName, string $routePath, string $controller): void
    {
        $this->routeCollection->add(
            $routeName,
            new Route($routePath, ['_controller' => $controller])
        );
    }

    /**
     * @return ClassMetadata|MockObject
     * @throws \ReflectionException
     */
    private function mockClassMetadata(string $class, ?array $fieldNames = null, ?array $associationNames = null): ClassMetadata|MockObject
    {
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('getReflectionClass')->willReturn(new \ReflectionClass($class));
        $metadata->method('getFieldNames')->willReturn($fieldNames);
        $metadata->method('getAssociationNames')->willReturn($associationNames);
        return $metadata;
    }
}
