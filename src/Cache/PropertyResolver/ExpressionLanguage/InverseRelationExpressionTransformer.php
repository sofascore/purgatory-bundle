<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\Cache\PropertyResolver\ExpressionLanguage;

use Sofascore\PurgatoryBundle\Exception\AccessorNotInferableException;
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
        $accessor = $this->createAccessor($class, $property);
        $inverseExpression = str_replace('obj', 'obj.'.$accessor, (string) $expression);

        return new Expression("obj.$accessor !== null ? ($inverseExpression) : $fallback");
    }

    private function createAccessor(string $class, string $property): string
    {
        if (null === $readInfo = $this->extractor->getReadInfo($class, $property)) {
            throw new AccessorNotInferableException($class, $property);
        }

        /** @var PropertyReadInfo::TYPE_* $type */
        $type = $readInfo->getType();

        return match ($type) {
            PropertyReadInfo::TYPE_METHOD => $readInfo->getName().'()',
            PropertyReadInfo::TYPE_PROPERTY => $readInfo->getName(),
        };
    }
}
