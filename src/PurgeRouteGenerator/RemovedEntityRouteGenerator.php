<?php

declare(strict_types=1);

namespace Sofascore\PurgatoryBundle2\PurgeRouteGenerator;

use Doctrine\Persistence\ManagerRegistry;
use Sofascore\PurgatoryBundle2\Cache\Configuration\ConfigurationLoaderInterface;
use Sofascore\PurgatoryBundle2\Exception\EntityMetadataNotFoundException;
use Sofascore\PurgatoryBundle2\Listener\Enum\Action;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

final class RemovedEntityRouteGenerator extends AbstractEntityRouteGenerator
{
    public function __construct(
        ConfigurationLoaderInterface $configurationLoader,
        PropertyAccessorInterface $propertyAccessor,
        ?ExpressionLanguage $expressionLanguage,
        private readonly ManagerRegistry $managerRegistry,
    ) {
        parent::__construct($configurationLoader, $propertyAccessor, $expressionLanguage);
    }

    public function supports(Action $action, object $entity): bool
    {
        return Action::Delete === $action;
    }

    protected function getChangedProperties(object $entity, array $entityChangeSet): array
    {
        $class = $entity::class;

        if (null === $metadata = $this->managerRegistry->getManagerForClass($class)?->getClassMetadata($class)) {
            throw new EntityMetadataNotFoundException($class);
        }

        return array_merge(
            $metadata->getFieldNames(),
            $metadata->getAssociationNames(),
        );
    }

    protected function getRouteParameterValues(object $entity, array $entityChangeSet, string $property): array
    {
        /** @var scalar $value */
        $value = $this->propertyAccessor->getValue($entity, $property);

        return [$value];
    }
}
