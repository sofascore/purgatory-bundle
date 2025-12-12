<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\RouteParamValueResolver;

use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\ExpressionValues;
use Sofascore\PurgatoryBundle\Exception\LogicException;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * @implements ValuesResolverInterface<array{0: Expression}>
 */
final class ExpressionValuesResolver implements ValuesResolverInterface
{
    public function __construct(
        private readonly ?ExpressionLanguage $expressionLanguage,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public static function for(): string
    {
        return ExpressionValues::type();
    }

    /**
     * {@inheritDoc}
     */
    public function resolve(array $unresolvedValues, object $entity): array
    {
        $expression = $unresolvedValues[0];

        /** @var scalar|list<?scalar>|null $values */
        $values = $this->getExpressionLanguage()->evaluate($expression, ['obj' => $entity]);

        return \is_array($values) ? $values : [$values];
    }

    private function getExpressionLanguage(): ExpressionLanguage
    {
        return $this->expressionLanguage
            ?? throw new LogicException('You cannot use expressions because the Symfony ExpressionLanguage component is not installed. Try running "composer require symfony/expression-language".');
    }
}
