<?php

namespace SofaScore\Purgatory\Mapping\Loader;

use AnnotationReader\Fixtures\Entity1;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SofaScore\Purgatory\Annotation\SubscribeTo;
use SofaScore\Purgatory\AnnotationReader\Reader;
use SofaScore\Purgatory\Mapping\MappingCollection;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertEquals;

/**
 * @covers \SofaScore\Purgatory\Mapping\Loader\AnnotationsLoader
 * @covers \SofaScore\Purgatory\Mapping\MappingCollection
 * @covers \SofaScore\Purgatory\Mapping\MappingValue
 * @covers \SofaScore\Purgatory\Mapping\PropertySubscription
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

        self::assertDirectoryDoesNotExist('./cache_refresh');

        $configurationMock->expects(self::exactly(2))->method('getCacheDir')->willReturn('.');
        $configurationMock->expects(self::once())->method('getDebug')->willReturn(true);

        $readerMock->method('getAnnotations')->willReturn(
            [SubscribeTo::class => [new SubscribeTo(['value' => Entity1::class])]]
        );
        $objectManagerMock->method('getClassMetadata')->willReturn($this->mockClassMetadata(Entity1::class));

        $loader = new AnnotationsLoader(...$mocks);
        $loader->load();

        self::assertDirectoryExists('./cache_refresh');
        /** @var MappingCollection $result */
        $result = require './cache_refresh/mappings/collection.php';
        self::assertNotEmpty($result);
        assertCount(1, $result);
        assertEquals('app_api_v1_sport_list', $result->get('\AnnotationReader\Fixtures\Entity1')[0]->getRouteName());
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

        self::assertDirectoryDoesNotExist('./cache_refresh');

        $configurationMock->expects(self::exactly(2))->method('getCacheDir')->willReturn('.');
        $configurationMock->expects(self::once())->method('getDebug')->willReturn(true);

        $readerMock->method('getAnnotations')->willReturn(
            [SubscribeTo::class => [new SubscribeTo(['value' => Entity1::class, 'properties' => ['name']])]]
        );
        $objectManagerMock->method('getClassMetadata')->willReturn(
            $this->mockClassMetadata(Entity::class, ['name', 'id', 'createdAt'])
        );

        $loader = new AnnotationsLoader(...$mocks);
        $loader->load();

        self::assertDirectoryExists('./cache_refresh');
        /** @var MappingCollection $result */
        $result = require './cache_refresh/mappings/collection.php';
        self::assertNotEmpty($result);
        assertCount(1, $result);
        assertEquals('app_api_v1_sport_list', $result->get('\AnnotationReader\Fixtures\Entity1::name')[0]->getRouteName());
    }

    protected function setUp(): void
    {
        if (file_exists('./cache_refresh/mappings/collection.php')) {
            unlink('./cache_refresh/mappings/collection.php');
        }
        if (file_exists('./cache_refresh/mappings/collection.php.meta')) {
            unlink('./cache_refresh/mappings/collection.php.meta');
        }
        if (is_dir('./cache_refresh/mappings')) {
            rmdir('./cache_refresh/mappings');
        }
        if (is_dir('./cache_refresh')) {
            rmdir('./cache_refresh');
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
     */
    private function mockClassMetadata(string $class, ?array $fieldNames = null, ?array $associationNames = null)
    {
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('getReflectionClass')->willReturn(new \ReflectionClass($class));
        $metadata->method('getFieldNames')->willReturn($fieldNames);
        $metadata->method('getAssociationNames')->willReturn($associationNames);
        return $metadata;
    }
}
