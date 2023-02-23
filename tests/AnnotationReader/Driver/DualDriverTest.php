<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\AnnotationReader\Driver;

use Doctrine\Common\Annotations\AnnotationReader;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\AnnotationReader\AttributeReader;
use Sofascore\PurgatoryBundle\AnnotationReader\Driver\DualDriver;
use Sofascore\PurgatoryBundle\Tests\AnnotationReader\Fixtures\DualClass;
use Sofascore\PurgatoryBundle\Tests\AnnotationReader\Fixtures\SillyLittleAnnotation;

/**
 * @coversDefaultClass \Sofascore\PurgatoryBundle\AnnotationReader\Driver\DualDriver
 */
class DualDriverTest extends TestCase
{
    private DualDriver $driver;

    protected function setUp(): void
    {
        $this->driver = new DualDriver(new AnnotationReader(), new AttributeReader());
    }

    protected function tearDown(): void
    {
        unset($this->driver);
    }

    /**
     * @covers ::getClassAnnotations
     */
    public function testGetClassAnnotations(): void
    {
        $annotations = $this->driver->getClassAnnotations(new \ReflectionClass(DualClass::class));

        self::assertCount(2, $annotations);
        self::assertInstanceOf(SillyLittleAnnotation::class, $annotations[0]);
        self::assertInstanceOf(SillyLittleAnnotation::class, $annotations[1]);
    }

    /**
     * @covers ::getClassAnnotation
     */
    public function testGetClassAnnotation(): void
    {
        $annotation = $this->driver->getClassAnnotation(
            new \ReflectionClass(DualClass::class),
            SillyLittleAnnotation::class
        );

        self::assertInstanceOf(SillyLittleAnnotation::class, $annotation);
        self::assertEquals('annotation', $annotation->getValue());
    }

    /**
     * @covers ::getMethodAnnotations
     */
    public function testGetMethodAnnotations(): void
    {
        $annotations = $this->driver->getMethodAnnotations(
            new \ReflectionMethod(DualClass::class, 'methodWithBothAnnotationAndAttribute')
        );
        self::assertCount(2, $annotations);
        self::assertInstanceOf(SillyLittleAnnotation::class, $annotations[0]);
        self::assertInstanceOf(SillyLittleAnnotation::class, $annotations[1]);

        $annotations = $this->driver->getMethodAnnotations(
            new \ReflectionMethod(DualClass::class, 'methodWithAnnotation')
        );
        self::assertCount(1, $annotations);
        self::assertInstanceOf(SillyLittleAnnotation::class, $annotations[0]);
        self::assertEquals('annotation', $annotations[0]->getValue());

        $annotations = $this->driver->getMethodAnnotations(
            new \ReflectionMethod(DualClass::class, 'methodWithAttribute')
        );
        self::assertCount(1, $annotations);
        self::assertInstanceOf(SillyLittleAnnotation::class, $annotations[0]);
        self::assertEquals('attribute', $annotations[0]->getValue());

        $annotations = $this->driver->getMethodAnnotations(
            new \ReflectionMethod(DualClass::class, 'methodWithNoAnnotations')
        );
        self::assertCount(0, $annotations);
    }

    /**
     * @covers ::getMethodAnnotation
     */
    public function testGetMethodAnnotation(): void
    {
        $annotation = $this->driver->getMethodAnnotation(
            new \ReflectionMethod(DualClass::class, 'methodWithBothAnnotationAndAttribute'),
            SillyLittleAnnotation::class
        );
        self::assertInstanceOf(SillyLittleAnnotation::class, $annotation);
        self::assertEquals('annotation', $annotation->getValue());

        $annotation = $this->driver->getMethodAnnotation(
            new \ReflectionMethod(DualClass::class, 'methodWithAnnotation'),
            SillyLittleAnnotation::class
        );
        self::assertInstanceOf(SillyLittleAnnotation::class, $annotation);
        self::assertEquals('annotation', $annotation->getValue());

        $annotation = $this->driver->getMethodAnnotation(
            new \ReflectionMethod(DualClass::class, 'methodWithAttribute'),
            SillyLittleAnnotation::class
        );
        self::assertInstanceOf(SillyLittleAnnotation::class, $annotation);
        self::assertEquals('attribute', $annotation->getValue());

        $annotation = $this->driver->getMethodAnnotation(
            new \ReflectionMethod(DualClass::class, 'methodWithNoAnnotations'),
            SillyLittleAnnotation::class
        );
        self::assertNull($annotation);
    }

    /**
     * @covers ::getPropertyAnnotations
     */
    public function testGetPropertyAnnotations(): void
    {
        $annotations = $this->driver->getPropertyAnnotations(
            new \ReflectionProperty(DualClass::class, 'propertyWithBothAnnotationAndAttribute')
        );
        self::assertCount(2, $annotations);
        self::assertInstanceOf(SillyLittleAnnotation::class, $annotations[0]);
        self::assertInstanceOf(SillyLittleAnnotation::class, $annotations[1]);

        $annotations = $this->driver->getPropertyAnnotations(
            new \ReflectionProperty(DualClass::class, 'propertyWithAnnotation')
        );
        self::assertCount(1, $annotations);
        self::assertInstanceOf(SillyLittleAnnotation::class, $annotations[0]);
        self::assertEquals('annotation', $annotations[0]->getValue());

        $annotations = $this->driver->getPropertyAnnotations(
            new \ReflectionProperty(DualClass::class, 'propertyWithAttribute')
        );
        self::assertCount(1, $annotations);
        self::assertInstanceOf(SillyLittleAnnotation::class, $annotations[0]);
        self::assertEquals('attribute', $annotations[0]->getValue());

        $annotations = $this->driver->getPropertyAnnotations(
            new \ReflectionProperty(DualClass::class, 'propertyWithNoAnnotations')
        );
        self::assertCount(0, $annotations);
    }

    /**
     * @covers ::getPropertyAnnotation
     */
    public function testGetPropertyAnnotation(): void
    {
        $annotation = $this->driver->getPropertyAnnotation(
            new \ReflectionProperty(DualClass::class, 'propertyWithBothAnnotationAndAttribute'),
            SillyLittleAnnotation::class
        );
        self::assertInstanceOf(SillyLittleAnnotation::class, $annotation);
        self::assertEquals('annotation', $annotation->getValue());

        $annotation = $this->driver->getPropertyAnnotation(
            new \ReflectionProperty(DualClass::class, 'propertyWithAnnotation'),
            SillyLittleAnnotation::class
        );
        self::assertInstanceOf(SillyLittleAnnotation::class, $annotation);
        self::assertEquals('annotation', $annotation->getValue());

        $annotation = $this->driver->getPropertyAnnotation(
            new \ReflectionProperty(DualClass::class, 'propertyWithAttribute'),
            SillyLittleAnnotation::class
        );
        self::assertInstanceOf(SillyLittleAnnotation::class, $annotation);
        self::assertEquals('attribute', $annotation->getValue());

        $annotation = $this->driver->getPropertyAnnotation(
            new \ReflectionProperty(DualClass::class, 'propertyWithNoAnnotations'),
            SillyLittleAnnotation::class
        );
        self::assertNull($annotation);
    }
}
