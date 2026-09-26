<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Test\PHPUnit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Test\PHPUnit\AttributeReader;
use Sofascore\PurgatoryBundle\Test\PHPUnit\WithEntityChangePurging;
use Sofascore\PurgatoryBundle\Tests\Test\PHPUnit\Fixtures\WithClassAttributeDummy;
use Sofascore\PurgatoryBundle\Tests\Test\PHPUnit\Fixtures\WithMethodAttributeDummy;

#[CoversClass(AttributeReader::class)]
final class AttributeReaderTest extends TestCase
{
    public function testForClass(): void
    {
        $reader = new AttributeReader();

        $attribute = $reader->forClass(WithClassAttributeDummy::class);

        self::assertInstanceOf(WithEntityChangePurging::class, $attribute);
        self::assertIsCached($attribute, $reader, WithClassAttributeDummy::class);
    }

    public function testForClassWithoutAttribute(): void
    {
        $reader = new AttributeReader();

        self::assertNull($reader->forClass(WithMethodAttributeDummy::class));
        self::assertIsCached(null, $reader, WithMethodAttributeDummy::class);
    }

    public function testForMethod(): void
    {
        $reader = new AttributeReader();

        $attribute = $reader->forMethod(WithMethodAttributeDummy::class, 'testWithAttribute');

        self::assertInstanceOf(WithEntityChangePurging::class, $attribute);
        self::assertIsCached($attribute, $reader, WithMethodAttributeDummy::class.'::testWithAttribute');
    }

    public function testForMethodWithoutAttribute(): void
    {
        $reader = new AttributeReader();

        self::assertNull($reader->forMethod(WithMethodAttributeDummy::class, 'testWithoutAttribute'));
        self::assertIsCached(null, $reader, WithMethodAttributeDummy::class.'::testWithoutAttribute');
    }

    public function testForMethodDoesNotLookAtTheClass(): void
    {
        $reader = new AttributeReader();

        self::assertNull($reader->forMethod(WithClassAttributeDummy::class, 'testWithoutAttribute'));
    }

    private static function assertIsCached(?WithEntityChangePurging $expected, AttributeReader $reader, string $key): void
    {
        /** @var array<string, ?WithEntityChangePurging> $cache */
        $cache = (new \ReflectionProperty(AttributeReader::class, 'cache'))->getValue($reader);

        self::assertArrayHasKey($key, $cache);
        self::assertSame($expected, $cache[$key]);
    }
}
