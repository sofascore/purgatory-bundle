<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Attribute;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsPurgatoryResolver extends AutoconfigureTag
{
    public function __construct(string $alias)
    {
        parent::__construct(
            name: 'purgatory.route_parameter_resolver_service',
            attributes: ['alias' => $alias],
        );
    }
}
