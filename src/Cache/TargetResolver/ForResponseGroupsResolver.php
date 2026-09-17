<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Cache\TargetResolver;

use Sofascore\PurgatoryBundle\Attribute\Target\ForGroups;
use Sofascore\PurgatoryBundle\Attribute\Target\ForResponseGroups;
use Sofascore\PurgatoryBundle\Attribute\Target\TargetInterface;
use Sofascore\PurgatoryBundle\Cache\RouteMetadata\RouteMetadata;
use Sofascore\PurgatoryBundle\Exception\LogicException;
use Symfony\Component\HttpKernel\Attribute\Serialize;

/**
 * @implements TargetResolverInterface<ForResponseGroups>
 */
final class ForResponseGroupsResolver implements TargetResolverInterface
{
    public function __construct(
        private readonly ForGroupsResolver $forGroupsResolver,
    ) {
        if (!class_exists(Serialize::class)) {
            throw new LogicException('You cannot use the "ForResponseGroups" attribute because the "#[Serialize]" attribute is not available. Try upgrading "symfony/http-kernel" to version 8.1 or higher.');
        }
    }

    /**
     * {@inheritDoc}
     */
    public static function for(): string
    {
        return ForResponseGroups::class;
    }

    /**
     * {@inheritDoc}
     */
    public function resolve(TargetInterface $target, RouteMetadata $routeMetadata): array
    {
        if (null === $reflectionMethod = $routeMetadata->reflectionMethod) {
            throw new LogicException(\sprintf('The "ForResponseGroups" attribute cannot be used for route "%s" because it is not backed by a controller method.', $routeMetadata->routeName));
        }

        $controller = $reflectionMethod->class.'::'.$reflectionMethod->name.'()';

        if (null === $attribute = $reflectionMethod->getAttributes(Serialize::class)[0] ?? null) {
            throw new LogicException(\sprintf('The "ForResponseGroups" attribute requires the "#[Serialize]" attribute on the "%s" controller method of route "%s".', $controller, $routeMetadata->routeName));
        }

        $groups = $attribute->newInstance()->context['groups'] ?? null;

        if (!self::isValidGroups($groups)) {
            throw new LogicException(\sprintf('The "ForResponseGroups" attribute requires the "#[Serialize]" attribute on the "%s" controller method of route "%s" to define at least one serialization group.', $controller, $routeMetadata->routeName));
        }

        return $this->forGroupsResolver->resolve(new ForGroups($groups), $routeMetadata);
    }

    /**
     * @phpstan-assert-if-true non-empty-string|non-empty-list<non-empty-string> $groups
     */
    private static function isValidGroups(mixed $groups): bool
    {
        if (\is_string($groups)) {
            return '' !== $groups;
        }

        return \is_array($groups)
            && [] !== $groups
            && array_is_list($groups)
            && array_all($groups, static fn (mixed $group): bool => \is_string($group) && '' !== $group);
    }
}
