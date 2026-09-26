<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Test\PHPUnit\Fixtures;

use Sofascore\PurgatoryBundle\Test\PHPUnit\WithEntityChangePurging;

#[WithEntityChangePurging]
class WithClassAttributeDummy
{
    public function testWithoutAttribute(): void
    {
    }

    #[WithEntityChangePurging]
    public function testWithAttribute(): void
    {
    }
}
