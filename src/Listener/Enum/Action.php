<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Listener\Enum;

enum Action: string
{
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
}
