<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Cache\TargetResolver;

use Sofascore\PurgatoryBundle2\Attribute\Target\ForGroups;
use Sofascore\PurgatoryBundle2\Attribute\Target\TargetInterface;
use Sofascore\PurgatoryBundle2\Cache\ControllerMetadata\ControllerMetadata;
use Sofascore\PurgatoryBundle2\Exception\InvalidArgumentException;
use Sofascore\PurgatoryBundle2\Exception\LogicException;
use Sofascore\PurgatoryBundle2\Exception\RuntimeException;
use Symfony\Component\PropertyInfo\PropertyListExtractorInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class ForGroupsResolver implements TargetResolverInterface
{
    public function __construct(
        private readonly PropertyListExtractorInterface $propertyListExtractor,
    ) {
        if (!interface_exists(SerializerInterface::class)) {
            throw new LogicException('You cannot use the "ForGroups" attribute because the Symfony Serializer component is not installed. Try running "composer require symfony/serializer".');
        }
    }

    /**
     * {@inheritDoc}
     */
    public static function for(): string
    {
        return ForGroups::class;
    }

    /**
     * {@inheritDoc}
     */
    public function resolve(TargetInterface $target, ControllerMetadata $controllerMetadata): array
    {
        if (!$target instanceof ForGroups) {
            throw new InvalidArgumentException(sprintf('Target must be an instance of "%s".', ForGroups::class));
        }

        /** @var list<string>|null $resolvedProperties */
        $resolvedProperties = $this->propertyListExtractor->getProperties(
            $controllerMetadata->purgeOn->class,
            ['serializer_groups' => $target->groups],
        );

        if (null === $resolvedProperties) {
            throw new RuntimeException(sprintf('Could not resolve properties for groups "%s" in class "%s".', implode('", "', $target->groups), $controllerMetadata->purgeOn->class));
        }

        return $resolvedProperties;
    }
}
