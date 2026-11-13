<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Cache\RouteMetadata;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Cache\RouteMetadata\ContextMerger;
use Sofascore\PurgatoryBundle\Tests\Fixtures\DummyStringEnum;

#[CoversClass(ContextMerger::class)]
final class ContextMergerTest extends TestCase
{
    #[DataProvider('mergeProvider')]
    public function testMerge(array $context, array $overrides, array $expected): void
    {
        self::assertSame($expected, ContextMerger::merge($context, $overrides));
    }

    public static function mergeProvider(): iterable
    {
        yield 'empty context returns the overrides unchanged' => [
            'context' => [],
            'overrides' => ['ttl' => 5, 'tags' => ['social', 'social']],
            'expected' => ['ttl' => 5, 'tags' => ['social', 'social']],
        ];

        yield 'empty overrides return the context unchanged' => [
            'context' => ['ttl' => 5, 'tags' => ['social', 'social']],
            'overrides' => [],
            'expected' => ['ttl' => 5, 'tags' => ['social', 'social']],
        ];

        yield 'keys of the context keep their order and new keys are added at the end' => [
            'context' => ['a' => 1, 'b' => 2],
            'overrides' => ['c' => 3, 'b' => 4, 'a' => 5],
            'expected' => ['a' => 5, 'b' => 4, 'c' => 3],
        ];

        yield 'scalar replaces scalar' => [
            'context' => ['ttl' => 60, 'enabled' => true],
            'overrides' => ['ttl' => 5.5, 'enabled' => false],
            'expected' => ['ttl' => 5.5, 'enabled' => false],
        ];

        yield 'enum replaces enum' => [
            'context' => ['case' => DummyStringEnum::Case1],
            'overrides' => ['case' => DummyStringEnum::Case2],
            'expected' => ['case' => DummyStringEnum::Case2],
        ];

        yield 'null replaces list' => [
            'context' => ['tags' => ['social']],
            'overrides' => ['tags' => null],
            'expected' => ['tags' => null],
        ];

        yield 'scalar replaces array with keys' => [
            'context' => ['varnish' => ['ttl' => 60]],
            'overrides' => ['varnish' => 'off'],
            'expected' => ['varnish' => 'off'],
        ];

        yield 'array replaces scalar' => [
            'context' => ['varnish' => 'off'],
            'overrides' => ['varnish' => ['ttl' => 5]],
            'expected' => ['varnish' => ['ttl' => 5]],
        ];

        yield 'array replaces null' => [
            'context' => ['tags' => null],
            'overrides' => ['tags' => ['social']],
            'expected' => ['tags' => ['social']],
        ];

        yield 'arrays with keys are merged key by key' => [
            'context' => ['varnish' => ['tags' => ['social'], 'ttl' => 60]],
            'overrides' => ['varnish' => ['ttl' => 5]],
            'expected' => ['varnish' => ['tags' => ['social'], 'ttl' => 5]],
        ];

        yield 'arrays with keys are merged on every level' => [
            'context' => ['a' => ['b' => ['c' => ['x'], 'd' => 1]]],
            'overrides' => ['a' => ['b' => ['c' => ['y']]]],
            'expected' => ['a' => ['b' => ['c' => ['x', 'y'], 'd' => 1]]],
        ];

        yield 'integer keys that do not make a list are merged key by key' => [
            'context' => ['codes' => [404 => 'a']],
            'overrides' => ['codes' => [500 => 'b', 404 => 'c']],
            'expected' => ['codes' => [404 => 'c', 500 => 'b']],
        ];

        yield 'lists are appended' => [
            'context' => ['tags' => ['social', 'feed']],
            'overrides' => ['tags' => ['live']],
            'expected' => ['tags' => ['social', 'feed', 'live']],
        ];

        yield 'top level lists are appended' => [
            'context' => ['social'],
            'overrides' => ['live'],
            'expected' => ['social', 'live'],
        ];

        yield 'appended lists keep the first occurrence of a duplicate' => [
            'context' => ['tags' => ['social', 'feed', 'social']],
            'overrides' => ['tags' => ['live', 'feed', 'live']],
            'expected' => ['tags' => ['social', 'feed', 'live']],
        ];

        yield 'appended lists compare items strictly' => [
            'context' => ['ids' => [1, '1', 1.0, true]],
            'overrides' => ['ids' => ['1', 1, null, false]],
            'expected' => ['ids' => [1, '1', 1.0, true, null, false]],
        ];

        yield 'appended lists remove duplicate enums' => [
            'context' => ['cases' => [DummyStringEnum::Case1]],
            'overrides' => ['cases' => [DummyStringEnum::Case2, DummyStringEnum::Case1]],
            'expected' => ['cases' => [DummyStringEnum::Case1, DummyStringEnum::Case2]],
        ];

        yield 'appended lists remove duplicate arrays' => [
            'context' => ['rules' => [['a' => 1]]],
            'overrides' => ['rules' => [['a' => 1], ['b' => 2]]],
            'expected' => ['rules' => [['a' => 1], ['b' => 2]]],
        ];

        yield 'empty list does not change a list' => [
            'context' => ['tags' => ['social']],
            'overrides' => ['tags' => []],
            'expected' => ['tags' => ['social']],
        ];

        yield 'empty list does not change an array with keys' => [
            'context' => ['varnish' => ['ttl' => 60]],
            'overrides' => ['varnish' => []],
            'expected' => ['varnish' => ['ttl' => 60]],
        ];

        yield 'array with keys is merged key by key into a list' => [
            'context' => ['mixed' => ['a', 'b']],
            'overrides' => ['mixed' => ['key' => 'value']],
            'expected' => ['mixed' => ['a', 'b', 'key' => 'value']],
        ];

        yield 'list is merged key by key into an array with keys' => [
            'context' => ['mixed' => ['key' => 'value']],
            'overrides' => ['mixed' => ['a']],
            'expected' => ['mixed' => ['key' => 'value', 0 => 'a']],
        ];
    }
}
