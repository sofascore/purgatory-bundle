<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\Cache\PropertyResolver;

use Doctrine\ORM\Mapping\AssociationMapping;
use Doctrine\ORM\Mapping\ClassMetadata as ORMClassMetadata;
use Doctrine\ORM\Mapping\OneToOneOwningSideMapping;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Sofascore\PurgatoryBundle2\Cache\Metadata\ControllerMetadata;
use Sofascore\PurgatoryBundle2\Cache\Metadata\PurgeSubscription;
use Sofascore\PurgatoryBundle2\Exception\PropertyNotAccessibleException;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\PropertyInfo\PropertyReadInfo;
use Symfony\Component\PropertyInfo\PropertyReadInfoExtractorInterface;

final class AssociationResolver implements SubscriptionResolverInterface
{
    public function __construct(
        private readonly PropertyReadInfoExtractorInterface $extractor,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function resolveSubscription(
        ControllerMetadata $controllerMetadata,
        ClassMetadata $classMetadata,
        array $routeParams,
        string $target,
    ): \Generator {
        if (!$classMetadata instanceof ORMClassMetadata || !$classMetadata->hasAssociation($target)) {
            return false;
        }

        /** @var AssociationMapping|array{type: int, inversedBy?: ?string} $associationMapping */
        $associationMapping = $classMetadata->getAssociationMapping($target);

        /** @var int $associationType */
        $associationType = $associationMapping instanceof AssociationMapping
            ? $associationMapping->type()
            : $associationMapping['type'];

        if (ORMClassMetadata::ONE_TO_ONE === $associationType) {
            if ($classMetadata->isAssociationInverseSide($target)) {
                $associationTarget = $classMetadata->getAssociationMappedByTargetField($target);
            } else {
                /** @var ?string $associationTarget */
                $associationTarget = $associationMapping instanceof OneToOneOwningSideMapping
                    ? $associationMapping->inversedBy
                    : $associationMapping['inversedBy'] ?? null;
            }
        } elseif (ORMClassMetadata::ONE_TO_MANY === $associationType) {
            $associationTarget = $classMetadata->getAssociationMappedByTargetField($target);
        } else {
            return false;
        }

        if (null === $associationTarget) {
            return false;
        }

        $associationClass = $classMetadata->getAssociationTargetClass($target);

        /** @var array<string, list<string>> $transformedRouteParams */
        $transformedRouteParams = [];
        if ([] !== $routeParams) {
            /** @var string|string[] $values */
            foreach ($routeParams as $routeParam => $values) {
                foreach ((array) $values as $value) {
                    $transformedRouteParams[$routeParam][] = $this->transformRouteParameterValueForAssociation($value, $associationTarget);
                }
            }
        }

        if (null !== $if = $controllerMetadata->purgeOn->if) {
            $expression = (string) $if;
            $if = new Expression(str_replace('obj', 'obj.'.$this->createGetter($associationClass, $associationTarget), $expression));
        }

        yield new PurgeSubscription(
            class: $associationClass,
            property: null,
            routeParams: $transformedRouteParams,
            routeName: $controllerMetadata->routeName,
            route: $controllerMetadata->route,
            if: $if,
        );

        return true;
    }

    private function transformRouteParameterValueForAssociation(string $value, string $associationTarget): string
    {
        return '@' === $value[0] ? $value : sprintf('%s.%s', $associationTarget, $value);
    }

    private function createGetter(string $class, string $property): string
    {
        if (null === $readInfo = $this->extractor->getReadInfo($class, $property)) {
            throw new PropertyNotAccessibleException($class, $property);
        }

        /** @var PropertyReadInfo::TYPE_* $type */
        $type = $readInfo->getType();

        return match ($type) {
            PropertyReadInfo::TYPE_METHOD => $readInfo->getName().'()',
            PropertyReadInfo::TYPE_PROPERTY => $readInfo->getName(),
        };
    }
}
