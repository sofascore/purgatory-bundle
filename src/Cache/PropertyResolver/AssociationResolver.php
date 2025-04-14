<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Cache\PropertyResolver;

use Doctrine\ORM\Mapping\AssociationMapping;
use Doctrine\ORM\Mapping\ClassMetadata as ORMClassMetadata;
use Doctrine\ORM\Mapping\OneToOneOwningSideMapping;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\InverseValuesAwareInterface;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\ValuesInterface;
use Sofascore\PurgatoryBundle\Cache\RouteMetadata\RouteMetadata;
use Sofascore\PurgatoryBundle\Cache\Subscription\PurgeSubscription;
use Sofascore\PurgatoryBundle\Exception\PropertyNotAccessibleException;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\Node\ArgumentsNode;
use Symfony\Component\ExpressionLanguage\Node\ConstantNode;
use Symfony\Component\ExpressionLanguage\Node\GetAttrNode;
use Symfony\Component\ExpressionLanguage\Node\NameNode;
use Symfony\Component\ExpressionLanguage\Node\Node;
use Symfony\Component\PropertyInfo\PropertyReadInfo;
use Symfony\Component\PropertyInfo\PropertyReadInfoExtractorInterface;

final class AssociationResolver implements SubscriptionResolverInterface
{
    public function __construct(
        private readonly PropertyReadInfoExtractorInterface $extractor,
        private readonly ?ExpressionLanguage $expressionLanguage,
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
            $inverseRouteParams[$routeParam] = $this->getInverseValuesFor($values, $associationTarget);
        }

        if (null !== $if = $routeMetadata->purgeOn->if) {
            $if = $this->createInverseExpression($if, $associationClass, $associationTarget);
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

    private function getInverseValuesFor(ValuesInterface $values, string $associationTarget): ValuesInterface
    {
        return $values instanceof InverseValuesAwareInterface ? $values->buildInverseValuesFor($associationTarget) : $values;
    }

    private function createInverseExpression(
        Expression $expression,
        string $associationClass,
        string $associationTarget,
    ): Expression {
        if (null === $readInfo = $this->extractor->getReadInfo($associationClass, $associationTarget)) {
            throw new PropertyNotAccessibleException($associationClass, $associationTarget);
        }

        /** @var PropertyReadInfo::TYPE_* $type */
        $type = $readInfo->getType();
        $name = $readInfo->getName();

        [$callType, $getter] = match ($type) {
            PropertyReadInfo::TYPE_METHOD => [GetAttrNode::METHOD_CALL, "$name()"],
            PropertyReadInfo::TYPE_PROPERTY => [GetAttrNode::PROPERTY_CALL, $name],
        };

        if (null === $node = $this->expressionLanguage?->parse($expression, ['obj'])->getNodes()) {
            throw new \RuntimeException('Could not parse expression: '.(string) $expression);
        }

        $inverseIf = $this->replaceObjWithInverse($node, $name, $callType)->dump();

        return new Expression("obj.$getter !== null && ($inverseIf)");
    }

    /**
     * @param GetAttrNode::PROPERTY_CALL|GetAttrNode::METHOD_CALL $type
     */
    private function replaceObjWithInverse(Node $node, string $inverse, int $type): Node
    {
        if ($node instanceof NameNode && 'obj' === $node->attributes['name']) {
            return new GetAttrNode(
                node: new NameNode('obj'),
                attribute: new ConstantNode(
                    value: $inverse,
                    isIdentifier: true,
                ),
                arguments: new ArgumentsNode(),
                type: $type,
            );
        }

        $newNode = clone $node;
        $newNode->nodes = [];

        /**
         * @var string $key
         * @var Node   $childNode
         */
        foreach ($node->nodes as $key => $childNode) {
            $newNode->nodes[$key] = $this->replaceObjWithInverse($childNode, $inverse, $type);
        }

        return $newNode;
    }
}
