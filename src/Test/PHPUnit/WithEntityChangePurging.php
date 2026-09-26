<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Test\PHPUnit;

/**
 * Enables purging on entity changes for a test class or a single test method
 * when the "purge_on_entity_change" option is disabled. Requires the "test"
 * option and the PurgatoryExtension to be registered in the PHPUnit configuration.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final class WithEntityChangePurging
{
}
