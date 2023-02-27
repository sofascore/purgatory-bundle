<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests;

use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Mapping\Loader\AnnotationsLoader;
use Sofascore\PurgatoryBundle\Mapping\MappingCollection;
use Sofascore\PurgatoryBundle\Mapping\MappingValue;
use Sofascore\PurgatoryBundle\Purgatory;
use Sofascore\PurgatoryBundle\Tests\AnnotationReader\Fixtures\Entity1;
use Sofascore\PurgatoryBundle\Tests\AnnotationReader\Fixtures\Entity2;
use Symfony\Component\PropertyAccess\PropertyAccessor;


/**
 * @coversDefaultClass \Sofascore\PurgatoryBundle\Purgatory
 */
final class PurgatoryTest extends TestCase
{

    /**
     * @covers ::getRoutesToPurge
     */
    public function testGetRoutesToPurgeOneRoute(): void
    {
        $purgatoryService = new Purgatory($this->mockMappingsLoader(), new PropertyAccessor());

        $changedObject = $this->getChangedObject();
        $changedProperties = ['priority'];

        $routes = $purgatoryService->getRoutesToPurge($changedObject, $changedProperties);

        self::assertCount(1, $routes);

        $route = $routes[0];

        self::assertEquals('app_api_v1_entity1_list_of_entities2', $route['route']);
        self::assertEquals(['id' => 7], $route['params']);
    }

    /**
     * @covers ::getRoutesToPurge
     */
    public function testGetRoutesToPurgeTwoRoutes(): void
    {
        $purgatoryService = new Purgatory($this->mockMappingsLoader(), new PropertyAccessor());

        $changedObject = $this->getChangedObject();
        $changedProperties = ['count'];

        $routes = $purgatoryService->getRoutesToPurge($changedObject, $changedProperties);

        self::assertCount(2, $routes);

        $route = $routes[0];

        self::assertEquals('app_api_v1_entity1_list_of_entities2', $route['route']);
        self::assertEquals(['id' => 7], $route['params']);

        $route = $routes[1];

        self::assertEquals('app_api_v1_entity2_count', $route['route']);
        self::assertEquals(['id' => 1], $route['params']);
    }

    private function mockMappingsLoader(): AnnotationsLoader
    {
        $loader = $this->createMock(AnnotationsLoader::class);

        $mappingCollection = $this->getMappingCollection();
        $loader->method('load')->willReturn($mappingCollection);

        return $loader;
    }


    private function getMappingCollection(): MappingCollection
    {
        $collection = new MappingCollection();

        $name = '\\' . ltrim(Entity2::class, '\\');
        $mappingValue = $this->getMappingValue('app_api_v1_entity1_list_of_entities2', ['id' => ['entity1.id']]);

        $collection->add($name, $mappingValue);

        $name = '\\' . ltrim(Entity2::class, '\\') . '::' . 'count';
        $mappingValue = $this->getMappingValue('app_api_v1_entity2_count', ['id' => ['id']]);

        $collection->add($name, $mappingValue);

        return $collection;
    }

    private function getMappingValue(
        string $routeName,
        ?array $parameters = null,
        ?string $if = null,
        ?array $tags = null
    ): MappingValue {
        $mappingValue = new MappingValue($routeName);
        $mappingValue->setParameters($parameters);
        $mappingValue->setIf($if);
        $mappingValue->setTags($tags);

        return $mappingValue;
    }

    private function getChangedObject(): Entity2
    {
        $entity1 = new Entity1();
        $entity1->setId(7);
        $entity1->setName('name');

        $entity2 = new Entity2();
        $entity2->setId(1);
        $entity2->setCount(2);
        $entity2->setEntity1($entity1);

        return $entity2;
    }
}
