<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Tests\Functional\TestApplication\Enum;

enum Country: string
{
    case Croatia = 'hr';
    case Iceland = 'is';
    case Norway = 'no';
    case Australia = 'au';
}
