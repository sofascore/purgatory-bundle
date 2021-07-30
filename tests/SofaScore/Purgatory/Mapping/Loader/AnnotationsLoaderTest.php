<?php

namespace SofaScore\Purgatory\Mapping\Loader;

use AnnotationReader\Fixtures\Entity1;
use Doctrine\ORM\Mapping\ClassMetadata;
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

use function PHPUnit\Framework\assertEquals;

/**
 * @covers \SofaScore\Purgatory\Mapping\Loader\AnnotationsLoader
 */
class AnnotationsLoaderTest extends TestCase
{
    private const TEST_CONTROLLER = 'App\\Controller\\TestController';

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
        $routerMock->expects(self::once())->method('getRouteCollection')->willReturn(new RouteCollection());

        $loader = new AnnotationsLoader(...$mocks);
        $loader->load();
    }

    public function testLoadOnConfiguredCacheDirSavesMappingsToCache()
    {
        $mocks = [
            $configurationMock,
            $routerMock,
            $controllerResolverMock,
            $readerMock,
            $objectManagerMock
        ] = $this->getMocks();

        self::assertDirectoryDoesNotExist('./cache_refresh');

        $routeCollection = $this->getRouteCollection();
        $configurationMock->expects(self::exactly(2))->method('getCacheDir')->willReturn('.');
        $configurationMock->expects(self::once())->method('getDebug')->willReturn(true);
        $routerMock->method('getRouteCollection')->willReturn($routeCollection);

        $controllerResolverMock->method('getController')->willReturn(
            [self::class, 'mockCallable']
        );
        $readerMock->method('getAnnotations')->willReturn(
            [SubscribeTo::class => [new SubscribeTo(['value' => Entity1::class])]]
        );
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('getReflectionClass')->willReturn(new \ReflectionClass(Entity1::class));
        $objectManagerMock->method('getClassMetadata')->willReturn($metadata);

        $loader = new AnnotationsLoader(...$mocks);
        $loader->load();

        self::assertDirectoryExists('./cache_refresh');
        /** @var MappingCollection $result */
        $result = require './cache_refresh/mappings/collection.php';
        self::assertNotEmpty($result);
        assertEquals('app_api_v1_sport_list', $result->get('\AnnotationReader\Fixtures\Entity1')[0]->getRouteName());
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
    }

    /**
     * @return MockObject[]
     */
    private function getMocks(): array
    {
        return [
            $this->createMock(Configuration::class),
            $this->createMock(RouterInterface::class),
            $this->createMock(ControllerResolverInterface::class),
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
}
