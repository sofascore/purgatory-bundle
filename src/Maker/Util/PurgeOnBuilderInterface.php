<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Maker\Util;

use Sofascore\PurgatoryBundle\Attribute\Target\ForGroups;

/**
 * @internal
 */
interface PurgeOnBuilderInterface
{
    public function generate(): string;

    /**
     * @param string|non-empty-list<string>|ForGroups $target
     */
    public function addTarget(string|array|ForGroups $target): self;

    /**
     * @param array<string, ?string> $routeParams
     */
    public function addRouteParams(array $routeParams): self;

    public function includeIf(): self;

    public function addRoute(string $route): self;

    /**
     * @param 'Create'|'Update'|'Delete'|non-empty-list<'Create'|'Update'|'Delete'> $actions
     */
    public function addActions(string|array $actions);
}
