<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Listener\Enum;

enum Action: string
{
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
}
