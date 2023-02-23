<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\AnnotationReader;

use Doctrine\Common\Annotations\AnnotationReader;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\AnnotationReader\Driver\DoctrineDriver;
use Sofascore\PurgatoryBundle\AnnotationReader\Reader;
use Sofascore\PurgatoryBundle\AnnotationReader\ReaderException;
use Sofascore\PurgatoryBundle\Tests\AnnotationReader\Fixtures\Entity1;
use Sofascore\PurgatoryBundle\Tests\AnnotationReader\Fixtures\ExtendedEntity1;

/**
 * @coversDefaultClass \Sofascore\PurgatoryBundle\AnnotationReader\Reader
 */
final class ReaderTest extends TestCase
{
    private Reader $reader;

    protected function setUp(): void
    {
        $this->reader = new Reader(new DoctrineDriver(new AnnotationReader()));
    }

    protected function tearDown(): void
    {
        unset($this->reader);
    }

    /**
     * @covers ::getAnnotations
     */
    public function testGetAnnotations(): void
    {
        $item = new \ReflectionClass(Entity1::class);
        self::assertCount(1, $this->reader->getAnnotations($item));
    }

    /**
     * @covers ::getAnnotations
     */
    public function testGetClassAnnotationsForExtendedClass(): void
    {
        $item = new \ReflectionClass(Entity1::class);
        self::assertCount(1, $this->reader->getAnnotations($item));

        $item = new \ReflectionClass(ExtendedEntity1::class);
        self::assertCount(1, $this->reader->getAnnotations($item));
    }

    /**
     * @covers ::getAnnotations
     */
    public function testGetPropertyAnnotationsForExtendedClass(): void
    {
        $item = new \ReflectionProperty(Entity1::class, 'name');
        self::assertCount(1, $this->reader->getAnnotations($item));

        $item = new \ReflectionProperty(ExtendedEntity1::class, 'name');
        self::assertCount(2, $this->reader->getAnnotations($item));
    }

    /**
     * @covers ::getItemAnnotations
     */
    public function testGetItemAnnotations(): void
    {
        $item = new \ReflectionClass(Entity1::class);
        self::assertCount(1, $this->reader->getItemAnnotations($item));

        $item = new \ReflectionMethod(Entity1::class, 'isEnabled');
        self::assertCount(1, $this->reader->getItemAnnotations($item));

        $item = new \ReflectionProperty(Entity1::class, 'id');
        self::assertCount(3, $this->reader->getItemAnnotations($item));
    }

    /**
     * @covers ::getItemClass
     */
    public function testGetItemClass(): void
    {
        $item = new \ReflectionClass(Entity1::class);
        self::assertSame(Entity1::class, $this->reader->getItemClass($item));

        $item = new \ReflectionMethod(Entity1::class, 'isEnabled');
        self::assertSame(Entity1::class, $this->reader->getItemClass($item));

        $item = new \ReflectionProperty(Entity1::class, 'id');
        self::assertSame(Entity1::class, $this->reader->getItemClass($item));

        $this->expectException(ReaderException::class);
        $this->reader->getItemClass(new \stdClass());
    }
}
