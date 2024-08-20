<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle\RouteProvider;

use Psr\Container\ContainerInterface;
use Sofascore\PurgatoryBundle\Attribute\RouteParamValue\PropertyValues;
use Sofascore\PurgatoryBundle\Cache\Configuration\ConfigurationLoaderInterface;
use Sofascore\PurgatoryBundle\Listener\Enum\Action;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyAccess\PropertyPath;

final class UpdatedEntityRouteProvider extends AbstractEntityRouteProvider
{
    public function __construct(
        ConfigurationLoaderInterface $configurationLoader,
        ?ExpressionLanguage $expressionLanguage,
        ContainerInterface $routeParamValueResolverLocator,
        private readonly PropertyAccessorInterface $propertyAccessor,
    ) {
        parent::__construct($configurationLoader, $expressionLanguage, $routeParamValueResolverLocator);
    }

    public function supports(Action $action, object $entity): bool
    {
        return Action::Update === $action;
    }

    /**
     * {@inheritDoc}
     */
    protected function getChangedProperties(object $entity, array $entityChangeSet): array
    {
        return array_keys($entityChangeSet);
    }

    /**
     * {@inheritDoc}
     */
    protected function processRouteParamValues(array $routeParamValues, array $routeParamConfigs, array $entityChangeSet): array
    {
        /** @var array<string, list<?scalar>> $oldRouteParamValues */
        $oldRouteParamValues = [];

        foreach ($routeParamConfigs as $param => $config) {
            if (PropertyValues::type() !== $config['type']) {
                continue;
            }

            $oldRouteParamValues[$param] = array_values(array_filter(
                $this->computeOldRouteParamValues($entityChangeSet, $config),
                static fn (mixed $value): bool => null !== $value,
            ));

            // remove param with no values
            if ([] === $oldRouteParamValues[$param]) {
                unset($oldRouteParamValues[$param]);
            }
        }

        // determine list of params with missing values
        $missingRouteParamValues = array_filter($routeParamValues, static fn (array $value): bool => [] === $value);

        // calculate the Cartesian product for all current params that have values
        $newValues = $this->getCartesianProduct(array_diff_key($routeParamValues, $missingRouteParamValues));
        // calculate the Cartesian product for all old values
        // this is done separately to avoid unnecessary old/new combinations
        // when there are multiple changed params
        $oldValues = [] === $oldRouteParamValues ? [] : $this->getCartesianProduct($oldRouteParamValues);

        $combinedValues = [];
        foreach ($newValues as $routeParams) {
            if ([] === $missingRouteParamValues) {
                // use new values when there are no missing params
                $combinedValues[] = $routeParams;
            }

            foreach ($oldValues as $oldRouteParams) {
                if ([] !== array_diff_key($missingRouteParamValues, $oldRouteParams)) {
                    // skip using old values if there are missing params
                    continue;
                }

                // merge old changed values with unchanged ones
                $combinedValues[] = array_merge($routeParams, $oldRouteParams);
            }
        }

        return $combinedValues;
    }

    /**
     * If possible, compute the old values for the route params.
     *
     * @param array<string, array{mixed, mixed}>                        $entityChangeSet
     * @param array{type: string, values: list<mixed>, optional?: true} $config
     *
     * @return list<?scalar>
     */
    private function computeOldRouteParamValues(array $entityChangeSet, array $config): array
    {
        /** @var list<?scalar> $values */
        $values = [];

        /** @var string $path */
        foreach ($config['values'] as $path) {
            if (isset($entityChangeSet[$path])) {
                /** @var scalar|list<?scalar>|null $old */
                $old = $entityChangeSet[$path][0];
                self::appendValues($values, $old);

                continue;
            }

            $propertyPath = new PropertyPath($path);

            if ($propertyPath->isIndex(0)) {
                continue;
            }

            // Embeddable check
            $fullPath = implode('.', $propertyPath->getElements());
            if (isset($entityChangeSet[$fullPath])) {
                /** @var scalar|list<?scalar>|null $old */
                $old = $entityChangeSet[$fullPath][0];
                self::appendValues($values, $old);

                continue;
            }

            // Association check
            $head = $propertyPath->getElement(0);

            if (!isset($entityChangeSet[$head][0])) {
                continue;
            }

            /** @var object|array<array-key, mixed> $old */
            $old = $entityChangeSet[$head][0];

            $subPath = ltrim(substr($path, \strlen($head)), '.?');

            if ($this->propertyAccessor->isReadable($old, $subPath)) {
                /** @var scalar|list<?scalar>|null $value */
                $value = $this->propertyAccessor->getValue($old, $subPath);
                self::appendValues($values, $value);
            }
        }

        return $values;
    }

    /**
     * @param list<?scalar>             $values
     * @param scalar|list<?scalar>|null $value
     */
    private static function appendValues(array &$values, int|float|string|bool|array|null $value): void
    {
        if (\is_array($value)) {
            array_push($values, ...$value);
        } else {
            $values[] = $value;
        }
    }
}
