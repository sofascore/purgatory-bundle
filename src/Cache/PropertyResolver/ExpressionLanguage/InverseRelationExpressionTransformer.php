<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Cache\PropertyResolver\ExpressionLanguage;

use Sofascore\PurgatoryBundle\Exception\PropertyNotAccessibleException;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\PropertyInfo\PropertyReadInfo;
use Symfony\Component\PropertyInfo\PropertyReadInfoExtractorInterface;

/**
 * @internal
 */
final class InverseRelationExpressionTransformer
{
    public function __construct(
        private readonly PropertyReadInfoExtractorInterface $extractor,
    ) {
    }

    public function transform(Expression $expression, string $class, string $property, string $fallback): Expression
    {
        $getter = $this->createGetter($class, $property);
        $inverseExpression = str_replace('obj', 'obj.'.$getter, (string) $expression);

        return new Expression("obj.$getter !== null ? ($inverseExpression) : $fallback");
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
