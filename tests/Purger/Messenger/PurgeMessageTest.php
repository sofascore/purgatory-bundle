<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Purger\Messenger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sofascore\PurgatoryBundle\Purger\Messenger\PurgeMessage;

#[CoversClass(PurgeMessage::class)]
final class PurgeMessageTest extends TestCase
{
    public function testExceptionIsThrownWhenArrayIsEmpty(): void
    {
        $this->expectException(\ValueError::class);
        $this->expectExceptionMessage('The list must contain at least one URL.');

        new PurgeMessage([]);
    }
}
