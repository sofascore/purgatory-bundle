<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\Functional\DebugCommand\Enum;

enum LanguageCodes: string
{
    case Croatia = 'hr';
    case Germany = 'de';
    case UnitedKingdom = 'en';
}
