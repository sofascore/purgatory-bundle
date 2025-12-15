<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Cache\PropertyResolver;

use Doctrine\ORM\Mapping\AssociationMapping;
use Doctrine\ORM\Mapping\ClassMetadata as ORMClassMetadata;
use Doctrine\ORM\Mapping\OneToOneOwningSideMapping;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Psr\Container\ContainerInterface;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\ValuesInterface;
use Sofascore\PurgatoryBundle\Cache\PropertyResolver\InverseValuesBuilder\InverseValuesBuilderInterface;
use Sofascore\PurgatoryBundle\Cache\RouteMetadata\RouteMetadata;
use Sofascore\PurgatoryBundle\Cache\Subscription\PurgeSubscription;
use Sofascore\PurgatoryBundle\Exception\PropertyNotAccessibleException;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\PropertyInfo\PropertyReadInfo;
use Symfony\Component\PropertyInfo\PropertyReadInfoExtractorInterface;

final class AssociationResolver implements SubscriptionResolverInterface
{
    public function __construct(
        private readonly PropertyReadInfoExtractorInterface $extractor,
        private readonly ContainerInterface $inverseValuesBuilderLocator,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function resolveSubscription(
        RouteMetadata $routeMetadata,
        ClassMetadata $classMetadata,
        array $routeParams,
        string $target,
    ): \Generator {
        if (!$classMetadata instanceof ORMClassMetadata || !$classMetadata->hasAssociation($target)) {
            return false;
        }

        /** @var AssociationMapping|array{type: int, inversedBy?: ?string} $associationMapping */
        $associationMapping = $classMetadata->getAssociationMapping($target);

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

        /** @var array<string, ValuesInterface> $inverseRouteParams */
        $inverseRouteParams = [];
        foreach ($routeParams as $routeParam => $values) {
            $inverseRouteParams[$routeParam] = $this->getInverseValuesBuilderFor($values)
                ?->build($values, $associationClass, $associationTarget)
                ?? $values;
        }

        if (null !== $if = $routeMetadata->purgeOn->if) {
            $expression = (string) $if;
            $getter = $this->createGetter($associationClass, $associationTarget);
            $inverseIf = str_replace('obj', 'obj.'.$getter, $expression);
            $if = new Expression("obj.$getter !== null && ($inverseIf)");
        }

        yield new PurgeSubscription(
            class: $associationClass,
            property: null,
            routeParams: $inverseRouteParams,
            routeName: $routeMetadata->routeName,
            route: $routeMetadata->route,
            actions: $routeMetadata->purgeOn->actions,
            if: $if,
        );

        return true;
    }

    /**
     * @template T of ValuesInterface
     *
     * @param T $values
     *
     * @return ?InverseValuesBuilderInterface<T>
     */
    private function getInverseValuesBuilderFor(ValuesInterface $values): ?InverseValuesBuilderInterface
    {
        /** @var ?InverseValuesBuilderInterface<T> $builder */
        $builder = $this->inverseValuesBuilderLocator->has($type = $values::type())
            ? $this->inverseValuesBuilderLocator->get($type)
            : null;

        return $builder;
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
