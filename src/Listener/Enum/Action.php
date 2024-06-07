<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Listener\Enum;

enum Action
{
    case Create;
    case Update;
    case Delete;
}
