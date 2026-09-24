<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Cache\Subscription;

use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\ValuesInterface;
use Sofascore\PurgatoryBundle\Listener\Enum\Action;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Routing\Route;

final class PurgeSubscription
{
    /**
     * @param class-string                   $class
     * @param array<string, ValuesInterface> $routeParams
     * @param ?non-empty-list<Action>        $actions
     * @param ?string                        $inversePropertyPath property path used to navigate from the changed
     *                                                            entity to the entity the closure expects, when the
     *                                                            closure `if` is attached to an inverse subscription
     */
    public function __construct(
        public readonly string $class,
        public readonly ?string $property,
        public readonly array $routeParams,
        public readonly string $routeName,
        public readonly Route $route,
        public readonly ?array $actions,
        public readonly \Closure|Expression|null $if = null,
        public readonly ?string $inversePropertyPath = null,
    ) {
    }
}
