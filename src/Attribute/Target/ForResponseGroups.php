<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Attribute\Target;

/**
 * Targets the properties that belong to the serialization groups configured
 * in the "#[Serialize]" attribute of the controller method.
 */
final class ForResponseGroups implements TargetInterface
{
}
