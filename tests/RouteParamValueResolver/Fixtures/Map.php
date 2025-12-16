<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Tests\RouteParamValueResolver\Fixtures;

enum Map: int
{
    public const NAMES = [
        'one' => self::One,
        'two' => self::Two,
    ];

    case One = 1;
    case Two = 2;
}
