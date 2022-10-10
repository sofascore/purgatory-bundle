<?php

declare(strict_types=1);

namespace SofaScore\Purgatory\Tests\AnnotationReader;

use PHPUnit\Framework\TestCase;
use SofaScore\Purgatory\AnnotationReader\AttributeReader;
use SofaScore\Purgatory\Tests\AnnotationReader\Fixtures\DualClass;
use SofaScore\Purgatory\Tests\AnnotationReader\Fixtures\SillyLittleAnnotation;

/**
 * @coversDefaultClass \SofaScore\Purgatory\AnnotationReader\AttributeReader
 */
class AttributeReaderTest extends TestCase
{
    private AttributeReader $reader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reader = new AttributeReader();
    }

    /**
     * @covers ::getClassAnnotations
     */
    public function testGetClassAnnotations(): void
    {
        $annotations = $this->reader->getClassAnnotations(new \ReflectionClass(DualClass::class));

        self::assertCount(1, $annotations);
        self::assertInstanceOf(SillyLittleAnnotation::class, $annotations[0]);
    }

    /**
     * @covers ::getClassAnnotation
     */
    public function testGetClassAnnotation(): void
    {
        $annotation = $this->reader->getClassAnnotation(
            new \ReflectionClass(DualClass::class),
            SillyLittleAnnotation::class
        );

        self::assertInstanceOf(SillyLittleAnnotation::class, $annotation);
        self::assertEquals('attribute', $annotation->getValue());
    }

    /**
     * @covers ::getMethodAnnotations
     */
    public function testGetMethodAnnotations(): void
    {
        $annotations = $this->reader->getMethodAnnotations(
            new \ReflectionMethod(DualClass::class, 'methodWithBothAnnotationAndAttribute')
        );
        self::assertCount(1, $annotations);
        self::assertInstanceOf(SillyLittleAnnotation::class, $annotations[0]);

        $annotations = $this->reader->getMethodAnnotations(
            new \ReflectionMethod(DualClass::class, 'methodWithAnnotation')
        );
        self::assertCount(0, $annotations);

        $annotations = $this->reader->getMethodAnnotations(
            new \ReflectionMethod(DualClass::class, 'methodWithAttribute')
        );
        self::assertCount(1, $annotations);
        self::assertInstanceOf(SillyLittleAnnotation::class, $annotations[0]);
        self::assertEquals('attribute', $annotations[0]->getValue());

        $annotations = $this->reader->getMethodAnnotations(
            new \ReflectionMethod(DualClass::class, 'methodWithNoAnnotations')
        );
        self::assertCount(0, $annotations);
    }

    /**
     * @covers ::getMethodAnnotation
     */
    public function testGetMethodAnnotation(): void
    {
        $annotation = $this->reader->getMethodAnnotation(
            new \ReflectionMethod(DualClass::class, 'methodWithBothAnnotationAndAttribute'),
            SillyLittleAnnotation::class
        );
        self::assertInstanceOf(SillyLittleAnnotation::class, $annotation);
        self::assertEquals('attribute', $annotation->getValue());

        $annotation = $this->reader->getMethodAnnotation(
            new \ReflectionMethod(DualClass::class, 'methodWithAnnotation'),
            SillyLittleAnnotation::class
        );
        self::assertNull($annotation);

        $annotation = $this->reader->getMethodAnnotation(
            new \ReflectionMethod(DualClass::class, 'methodWithAttribute'),
            SillyLittleAnnotation::class
        );
        self::assertInstanceOf(SillyLittleAnnotation::class, $annotation);
        self::assertEquals('attribute', $annotation->getValue());

        $annotation = $this->reader->getMethodAnnotation(
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
        $annotations = $this->reader->getPropertyAnnotations(
            new \ReflectionProperty(DualClass::class, 'propertyWithBothAnnotationAndAttribute')
        );
        self::assertCount(1, $annotations);
        self::assertInstanceOf(SillyLittleAnnotation::class, $annotations[0]);

        $annotations = $this->reader->getPropertyAnnotations(
            new \ReflectionProperty(DualClass::class, 'propertyWithAnnotation')
        );
        self::assertCount(0, $annotations);

        $annotations = $this->reader->getPropertyAnnotations(
            new \ReflectionProperty(DualClass::class, 'propertyWithAttribute')
        );
        self::assertCount(1, $annotations);
        self::assertInstanceOf(SillyLittleAnnotation::class, $annotations[0]);
        self::assertEquals('attribute', $annotations[0]->getValue());

        $annotations = $this->reader->getPropertyAnnotations(
            new \ReflectionProperty(DualClass::class, 'propertyWithNoAnnotations')
        );
        self::assertCount(0, $annotations);
    }

    /**
     * @covers ::getPropertyAnnotation
     */
    public function testGetPropertyAnnotation(): void
    {
        $annotation = $this->reader->getPropertyAnnotation(
            new \ReflectionProperty(DualClass::class, 'propertyWithBothAnnotationAndAttribute'),
            SillyLittleAnnotation::class
        );
        self::assertInstanceOf(SillyLittleAnnotation::class, $annotation);
        self::assertEquals('attribute', $annotation->getValue());

        $annotation = $this->reader->getPropertyAnnotation(
            new \ReflectionProperty(DualClass::class, 'propertyWithAnnotation'),
            SillyLittleAnnotation::class
        );
        self::assertNull($annotation);

        $annotation = $this->reader->getPropertyAnnotation(
            new \ReflectionProperty(DualClass::class, 'propertyWithAttribute'),
            SillyLittleAnnotation::class
        );
        self::assertInstanceOf(SillyLittleAnnotation::class, $annotation);
        self::assertEquals('attribute', $annotation->getValue());

        $annotation = $this->reader->getPropertyAnnotation(
            new \ReflectionProperty(DualClass::class, 'propertyWithNoAnnotations'),
            SillyLittleAnnotation::class
        );
        self::assertNull($annotation);
    }
}
