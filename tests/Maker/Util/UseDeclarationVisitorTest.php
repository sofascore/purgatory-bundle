<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Maker\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Maker\Util\UseDeclarationVisitor;

#[CoversClass(UseDeclarationVisitor::class)]
final class UseDeclarationVisitorTest extends TestCase
{
    // TODO

    public function testDummy(): void
    {
        self::assertTrue(true);
    }
}
