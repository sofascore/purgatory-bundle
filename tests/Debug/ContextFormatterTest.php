<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Debug;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Debug\ContextFormatter;
use Sofascore\PurgatoryBundle\Listener\Enum\Action;

#[CoversClass(ContextFormatter::class)]
final class ContextFormatterTest extends TestCase
{
    public function testContextIsCompactJson(): void
    {
        self::assertSame('{"throttle":8,"reasons":["comment"]}', (new ContextFormatter())->format(['throttle' => 8, 'reasons' => ['comment']]));
        self::assertSame('[{"throttle":8},[]]', (new ContextFormatter())->format([['throttle' => 8], []]));
    }

    public function testEnumsAreWrittenAsCases(): void
    {
        self::assertSame(
            '{"action":'.Action::class.'::Delete,"nested":['.Action::class.'::Create]}',
            (new ContextFormatter())->format(['action' => Action::Delete, 'nested' => [Action::Create]]),
        );
    }

    public function testStringThatLooksLikeAnEnumStaysAString(): void
    {
        self::assertSame(
            '{"action":'.json_encode(Action::class.'::Delete', \JSON_UNESCAPED_SLASHES).'}',
            (new ContextFormatter())->format(['action' => Action::class.'::Delete']),
        );
    }

    public function testFloatsKeepTheirFraction(): void
    {
        self::assertSame('{"weight":1.0,"count":1}', (new ContextFormatter())->format(['weight' => 1.0, 'count' => 1]));
    }

    public function testInvalidUtf8IsSubstituted(): void
    {
        self::assertSame("{\"name\":\"a\u{FFFD}b\"}", (new ContextFormatter())->format(['name' => "a\xFFb"]));
    }
}
