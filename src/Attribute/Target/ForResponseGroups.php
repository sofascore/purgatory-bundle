<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Attribute\Target;

use Sofascore\PurgatoryBundle\Exception\LogicException;
use Symfony\Component\HttpKernel\Attribute\Serialize;

/**
 * Targets the properties that belong to the serialization groups configured
 * in the "#[Serialize]" attribute of the controller method.
 */
final class ForResponseGroups implements TargetInterface
{
    public function __construct()
    {
        if (!class_exists(Serialize::class)) {
            throw new LogicException('You cannot use the "ForResponseGroups" attribute because the "#[Serialize]" attribute is not available. Try upgrading "symfony/http-kernel" to version 8.1 or higher.');
        }
    }
}
