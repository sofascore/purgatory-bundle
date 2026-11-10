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
            json_encode(['action' => Action::class.'::Delete'], \JSON_UNESCAPED_SLASHES),
            (new ContextFormatter())->format(['action' => Action::Delete]),
        );
    }

    public function testValuesJsonCannotHoldFallBackToVarExport(): void
    {
        self::assertSame("array ( 'limit' => INF, )", (new ContextFormatter())->format(['limit' => \INF]));
    }
}
