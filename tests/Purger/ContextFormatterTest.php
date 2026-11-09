<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Purger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Listener\Enum\Action;
use Sofascore\PurgatoryBundle\Purger\ContextFormatter;

#[CoversClass(ContextFormatter::class)]
final class ContextFormatterTest extends TestCase
{
    public function testContextIsCompactJson(): void
    {
        self::assertSame('{"throttle":8,"reasons":["comment"]}', ContextFormatter::format(['throttle' => 8, 'reasons' => ['comment']]));
        self::assertSame('[{"throttle":8},[]]', ContextFormatter::format([['throttle' => 8], []]));
    }

    public function testEnumsAreWrittenAsCases(): void
    {
        self::assertSame(
            json_encode(['action' => Action::class.'::Delete'], \JSON_UNESCAPED_SLASHES),
            ContextFormatter::format(['action' => Action::Delete]),
        );
    }

    public function testValuesJsonCannotHoldFallBackToVarExport(): void
    {
        self::assertSame("array ( 'limit' => INF, )", ContextFormatter::format(['limit' => \INF]));
    }
}
